<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Functions;

use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Core\Tool\ToolExecutionPolicy;
use SageGrids\PhpAiSdk\Event\EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\Events\ErrorOccurred;
use SageGrids\PhpAiSdk\Event\Events\MemoryLimitWarning;
use SageGrids\PhpAiSdk\Event\Events\RequestCompleted;
use SageGrids\PhpAiSdk\Event\Events\RequestStarted;
use SageGrids\PhpAiSdk\Event\Events\StreamChunkReceived;
use SageGrids\PhpAiSdk\Event\Events\ToolCallCompleted;
use SageGrids\PhpAiSdk\Event\Events\ToolCallStarted;
use SageGrids\PhpAiSdk\Exception\InputValidationException;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\Usage;

/**
 * Abstract base class for generation functions.
 *
 * Provides common functionality for parsing options, resolving providers,
 * and converting prompts to messages.
 */
abstract class AbstractGenerationFunction
{
    /** @var array<string, mixed> */
    protected array $options;

    protected TextProviderInterface $provider;
    protected string $model;

    /** @var Message[] */
    protected array $messages;

    protected ?string $system;
    protected ?int $maxTokens;
    protected ?float $temperature;
    protected ?float $topP;

    /** @var string[]|null */
    protected ?array $stopSequences;

    /** @var Tool[]|null */
    protected ?array $tools;

    protected string|Tool|null $toolChoice;

    /** @var callable|null */
    protected $onChunk;

    /** @var callable|null */
    protected $onFinish;

    protected int $maxToolRoundtrips;

    protected int $maxMessages;

    protected ?ToolExecutionPolicy $toolExecutionPolicy;

    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $this->options = AIConfig::mergeWithDefaults($options);
        $this->eventDispatcher = AIConfig::getEventDispatcher();
        $this->parseOptions();
    }

    /**
     * Parse and validate the options.
     *
     * @throws InputValidationException
     */
    protected function parseOptions(): void
    {
        // Resolve provider and model
        $this->resolveProvider();

        // Convert prompt to messages if provided
        $this->messages = $this->buildMessages();

        // Extract common options
        $this->system = $this->options['system'] ?? null;
        $this->maxTokens = isset($this->options['maxTokens']) ? (int) $this->options['maxTokens'] : null;
        $this->temperature = isset($this->options['temperature']) ? (float) $this->options['temperature'] : null;
        $this->topP = isset($this->options['topP']) ? (float) $this->options['topP'] : null;
        $this->stopSequences = $this->options['stopSequences'] ?? null;

        // Tool options
        $this->tools = $this->parseTools();
        $this->toolChoice = $this->options['toolChoice'] ?? null;

        // Callbacks
        $this->onChunk = $this->options['onChunk'] ?? null;
        $this->onFinish = $this->options['onFinish'] ?? null;

        // Max tool roundtrips
        $this->maxToolRoundtrips = $this->options['maxToolRoundtrips'] ?? AIConfig::getMaxToolRoundtrips();

        // Max messages (memory limit protection) - must be at least 1 to avoid division by zero
        $maxMessages = $this->options['maxMessages'] ?? AIConfig::getMaxMessages();
        $this->maxMessages = max(1, (int) $maxMessages);

        // Tool execution policy
        $this->toolExecutionPolicy = $this->options['toolExecutionPolicy'] ?? null;
    }

    /**
     * Resolve the provider from options.
     *
     * @throws InputValidationException
     */
    protected function resolveProvider(): void
    {
        $model = $this->options['model'] ?? AIConfig::getProvider();

        if ($model === null) {
            throw InputValidationException::requiredParameter('model');
        }

        if ($model instanceof ProviderInterface) {
            if (!$model instanceof TextProviderInterface) {
                throw InputValidationException::invalidParameterValue(
                    'model',
                    'Provider must implement TextProviderInterface',
                    $model::class
                );
            }
            $this->provider = $model;
            $this->model = $model->getName();
            return;
        }

        // Parse model string (e.g., 'openai/gpt-4o')
        $registry = ProviderRegistry::getInstance();
        $parsed = ProviderRegistry::parseModelString($model);

        $provider = $registry->get($parsed['provider']);
        if (!$provider instanceof TextProviderInterface) {
            throw InputValidationException::invalidParameterValue(
                'model',
                'Provider must implement TextProviderInterface',
                $parsed['provider']
            );
        }

        $this->provider = $provider;
        $this->model = $parsed['model'];
    }

    /**
     * Build messages from prompt and/or messages options.
     *
     * @return Message[]
     * @throws InputValidationException
     */
    protected function buildMessages(): array
    {
        $prompt = $this->options['prompt'] ?? null;
        $messages = $this->options['messages'] ?? [];

        if ($prompt === null && empty($messages)) {
            throw InputValidationException::invalidParameterValue(
                'prompt/messages',
                'Either "prompt" or "messages" must be provided'
            );
        }

        // Convert prompt to UserMessage if provided
        if ($prompt !== null) {
            $userMessage = new UserMessage($prompt);

            // If messages are also provided, prepend the prompt
            if (!empty($messages)) {
                array_unshift($messages, $userMessage);
            } else {
                $messages = [$userMessage];
            }
        }

        return $messages;
    }

    /**
     * Parse tools from options.
     *
     * @return Tool[]|null
     */
    protected function parseTools(): ?array
    {
        $tools = $this->options['tools'] ?? null;

        if ($tools === null) {
            return null;
        }

        if (!is_array($tools)) {
            return null;
        }

        // Ensure all items are Tool instances
        $parsedTools = [];
        foreach ($tools as $tool) {
            if ($tool instanceof Tool) {
                $parsedTools[] = $tool;
            }
        }

        return empty($parsedTools) ? null : $parsedTools;
    }

    /**
     * Parse schema from options (for object generation).
     *
     * @throws InputValidationException
     */
    protected function parseSchema(): Schema
    {
        $schema = $this->options['schema'] ?? null;

        if ($schema === null) {
            throw InputValidationException::requiredParameter('schema');
        }

        if ($schema instanceof Schema) {
            return $schema;
        }

        // If it's a class-string, use Schema::fromClass()
        if (is_string($schema) && class_exists($schema)) {
            return Schema::fromClass($schema);
        }

        throw InputValidationException::invalidParameterValue(
            'schema',
            'Schema must be a Schema instance or a valid class-string',
            $schema
        );
    }

    /**
     * Invoke the onChunk callback if set.
     */
    protected function invokeOnChunk(mixed $chunk): void
    {
        if ($this->onChunk !== null) {
            ($this->onChunk)($chunk);
        }
    }

    /**
     * Invoke the onFinish callback if set.
     */
    protected function invokeOnFinish(mixed $result): void
    {
        if ($this->onFinish !== null) {
            ($this->onFinish)($result);
        }
    }

    /**
     * Get the operation name for this function.
     */
    abstract protected function getOperationName(): string;

    /**
     * Dispatch a RequestStarted event.
     *
     * @param array<string, mixed> $parameters Additional parameters to include in the event.
     * @return float The start time for duration calculation.
     */
    protected function dispatchRequestStarted(array $parameters = []): float
    {
        $this->eventDispatcher->dispatch(
            RequestStarted::create(
                $this->provider->getName(),
                $this->model,
                $this->getOperationName(),
                $parameters,
            )
        );

        return microtime(true);
    }

    /**
     * Dispatch a RequestCompleted event.
     *
     * @param mixed $result The result of the request.
     * @param float $startTime The start time from dispatchRequestStarted.
     * @param Usage|null $usage Token usage statistics.
     */
    protected function dispatchRequestCompleted(mixed $result, float $startTime, ?Usage $usage = null): void
    {
        $this->eventDispatcher->dispatch(
            RequestCompleted::create(
                $this->provider->getName(),
                $this->model,
                $this->getOperationName(),
                $result,
                $startTime,
                $usage,
            )
        );
    }

    /**
     * Dispatch a StreamChunkReceived event.
     *
     * @param mixed $chunk The chunk that was received.
     * @param int $chunkIndex The index of the chunk.
     */
    protected function dispatchStreamChunkReceived(mixed $chunk, int $chunkIndex): void
    {
        $this->eventDispatcher->dispatch(
            new StreamChunkReceived(
                $this->provider->getName(),
                $this->model,
                $chunk,
                $chunkIndex,
            )
        );
    }

    /**
     * Dispatch a ToolCallStarted event.
     *
     * @param string $toolName The name of the tool being called.
     * @param array<string, mixed> $arguments The tool arguments.
     * @return float The start time for duration calculation.
     */
    protected function dispatchToolCallStarted(string $toolName, array $arguments): float
    {
        $this->eventDispatcher->dispatch(
            ToolCallStarted::create($toolName, $arguments)
        );

        return microtime(true);
    }

    /**
     * Dispatch a ToolCallCompleted event.
     *
     * @param string $toolName The name of the tool that was called.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param mixed $result The tool result.
     * @param float $startTime The start time from dispatchToolCallStarted.
     */
    protected function dispatchToolCallCompleted(
        string $toolName,
        array $arguments,
        mixed $result,
        float $startTime,
    ): void {
        $this->eventDispatcher->dispatch(
            ToolCallCompleted::create($toolName, $arguments, $result, $startTime)
        );
    }

    /**
     * Dispatch an ErrorOccurred event.
     *
     * @param \Throwable $exception The exception that occurred.
     */
    protected function dispatchErrorOccurred(\Throwable $exception): void
    {
        $this->eventDispatcher->dispatch(
            ErrorOccurred::create(
                $exception,
                $this->provider->getName(),
                $this->model,
                $this->getOperationName(),
            )
        );
    }

    /**
     * Dispatch a MemoryLimitWarning event.
     *
     * @param int $currentMessageCount Current number of messages.
     * @param int $roundtripCount Current roundtrip count.
     */
    protected function dispatchMemoryLimitWarning(int $currentMessageCount, int $roundtripCount): void
    {
        $this->eventDispatcher->dispatch(
            MemoryLimitWarning::create(
                $currentMessageCount,
                $this->maxMessages,
                $roundtripCount,
            )
        );
    }
}
