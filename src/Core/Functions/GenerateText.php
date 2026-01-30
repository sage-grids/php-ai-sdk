<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Functions;

use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Message\ToolMessage;
use SageGrids\PhpAiSdk\Core\Options\TextGenerationOptions;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Core\Tool\ToolExecutor;
use SageGrids\PhpAiSdk\Core\Tool\ToolRegistry;
use SageGrids\PhpAiSdk\Exception\MemoryLimitExceededException;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\Usage;

/**
 * Handles synchronous text generation with optional tool calling.
 */
final class GenerateText extends AbstractGenerationFunction
{
    /**
     * Create a new GenerateText instance.
     *
     * @param array<string, mixed>|TextGenerationOptions $options
     */
    public static function create(array|TextGenerationOptions $options): self
    {
        if ($options instanceof TextGenerationOptions) {
            $options = $options->toArray();
        }

        return new self($options);
    }

    /**
     * {@inheritDoc}
     */
    protected function getOperationName(): string
    {
        return 'generateText';
    }

    /**
     * Execute the text generation.
     *
     * @throws MemoryLimitExceededException When message count exceeds maxMessages limit.
     */
    public function execute(): TextResult
    {
        $startTime = $this->dispatchRequestStarted([
            'messageCount' => count($this->messages),
            'hasTools' => $this->tools !== null,
        ]);

        try {
            $messages = $this->messages;
            $roundtrip = 0;
            $warningDispatched = false;
            /** @var Usage[] $roundtripUsage */
            $roundtripUsage = [];

            while (true) {
                // Check memory limit before making API call
                $messageCount = count($messages);
                if ($messageCount > $this->maxMessages) {
                    throw MemoryLimitExceededException::messageLimitExceeded(
                        $messageCount,
                        $this->maxMessages,
                        $roundtrip,
                    );
                }

                // Dispatch warning at 80% of limit (only once)
                if (!$warningDispatched && $messageCount >= $this->maxMessages * 0.8) {
                    $this->dispatchMemoryLimitWarning($messageCount, $roundtrip);
                    $warningDispatched = true;
                }

                $result = $this->provider->generateText(
                    messages: $messages,
                    model: $this->model,
                    system: $this->system,
                    maxTokens: $this->maxTokens,
                    temperature: $this->temperature,
                    topP: $this->topP,
                    stopSequences: $this->stopSequences,
                    tools: $this->tools,
                    toolChoice: $this->toolChoice,
                );

                // If no tool calls or tool execution not needed, return the result
                if (!$result->hasToolCalls() || !$this->shouldExecuteTools()) {
                    $finalResult = $result->withAccumulatedUsage($roundtripUsage);
                    $this->invokeOnFinish($finalResult);
                    $this->dispatchRequestCompleted($finalResult, $startTime, $finalResult->usage);
                    return $finalResult;
                }

                // Check max roundtrips before continuing
                $roundtrip++;
                if ($roundtrip > $this->maxToolRoundtrips) {
                    $finalResult = $result->withAccumulatedUsage($roundtripUsage);
                    $this->invokeOnFinish($finalResult);
                    $this->dispatchRequestCompleted($finalResult, $startTime, $finalResult->usage);
                    return $finalResult;
                }

                // Track usage from this roundtrip (only if we're continuing the loop)
                if ($result->usage !== null) {
                    $roundtripUsage[] = $result->usage;
                }

                // Execute tools and continue conversation
                $messages = $this->executeToolsAndContinue($messages, $result);
            }
        } catch (\Throwable $e) {
            $this->dispatchErrorOccurred($e);
            throw $e;
        }
    }

    /**
     * Check if tools should be auto-executed.
     */
    private function shouldExecuteTools(): bool
    {
        if ($this->tools === null) {
            return false;
        }

        // Don't auto-execute if toolChoice is 'none'
        if ($this->toolChoice === 'none') {
            return false;
        }

        // Check if any tool is executable
        foreach ($this->tools as $tool) {
            if ($tool->isExecutable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute tool calls and append results to messages.
     *
     * @param Message[] $messages
     * @return Message[]
     */
    private function executeToolsAndContinue(array $messages, TextResult $result): array
    {
        // Build a registry from the available tools
        $registry = new ToolRegistry();
        foreach ($this->tools ?? [] as $tool) {
            $registry->set($tool);
        }

        // Create executor with security policy if configured
        $executor = new ToolExecutor($this->toolExecutionPolicy);

        // Add assistant message with tool calls
        $toolCallsArray = array_map(fn(ToolCall $tc) => $tc->toArray(), $result->toolCalls);
        $messages[] = new AssistantMessage($result->text, $toolCallsArray);

        // Execute each tool call
        foreach ($result->toolCalls as $toolCall) {
            $tool = $registry->get($toolCall->name);

            if ($tool === null) {
                // Tool not found, add error message
                $messages[] = new ToolMessage(
                    $toolCall->id,
                    "Error: Tool '{$toolCall->name}' not found"
                );
                continue;
            }

            // Dispatch tool call started event
            $toolStartTime = $this->dispatchToolCallStarted($toolCall->name, $toolCall->arguments);

            $toolResult = $executor->execute($tool, $toolCall);

            // Dispatch tool call completed event
            $this->dispatchToolCallCompleted(
                $toolCall->name,
                $toolCall->arguments,
                $toolResult->result,
                $toolStartTime,
            );

            // Convert result to string for the message
            $content = $toolResult->isSuccess()
                ? (is_string($toolResult->result) ? $toolResult->result : json_encode($toolResult->result))
                : "Error: {$toolResult->getErrorMessage()}";

            $messages[] = new ToolMessage($toolCall->id, $content);
        }

        return $messages;
    }
}
