<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Testing;

use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;

/**
 * Captures details of requests made to the FakeProvider for testing assertions.
 *
 * This class records all parameters passed to provider methods, allowing tests
 * to verify that the correct parameters were sent without actually making API calls.
 *
 * @example
 * ```php
 * $fake = new FakeProvider();
 * $fake->addTextResponse('Hello');
 *
 * AI::generateText(['model' => $fake, 'prompt' => 'Hi']);
 *
 * $request = $fake->getLastRequest();
 * $this->assertEquals('generateText', $request->operation);
 * $this->assertCount(1, $request->messages);
 * ```
 */
final readonly class RecordedRequest
{
    /**
     * @param string $operation The operation name (e.g., 'generateText', 'streamText', 'embed').
     * @param Message[] $messages The messages sent with the request.
     * @param string|null $system The system message if provided.
     * @param int|null $maxTokens Maximum tokens parameter.
     * @param float|null $temperature Temperature parameter.
     * @param float|null $topP Top-p parameter.
     * @param string[]|null $stopSequences Stop sequences if provided.
     * @param Tool[]|null $tools Tools if provided.
     * @param string|Tool|null $toolChoice Tool choice if provided.
     * @param Schema|null $schema Schema for object generation if provided.
     * @param array<string, mixed> $extraParams Any additional operation-specific parameters.
     * @param float $timestamp Unix timestamp when the request was recorded.
     */
    public function __construct(
        public string $operation,
        public array $messages = [],
        public ?string $system = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?array $stopSequences = null,
        public ?array $tools = null,
        public string|Tool|null $toolChoice = null,
        public ?Schema $schema = null,
        public array $extraParams = [],
        public float $timestamp = 0.0,
    ) {
    }

    /**
     * Check if the request has a specific message content.
     *
     * @param string $content The content to search for.
     * @return bool True if any message contains the content.
     */
    public function hasMessageContent(string $content): bool
    {
        foreach ($this->messages as $message) {
            if (str_contains((string) $message->content, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a specific tool was provided.
     *
     * @param string $toolName The name of the tool to check for.
     * @return bool True if the tool was provided.
     */
    public function hasTool(string $toolName): bool
    {
        if ($this->tools === null) {
            return false;
        }

        foreach ($this->tools as $tool) {
            if ($tool->name === $toolName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first message content.
     *
     * @return string|null The content of the first message, or null if no messages.
     */
    public function getFirstMessageContent(): ?string
    {
        if (empty($this->messages)) {
            return null;
        }

        $content = $this->messages[0]->content;

        return is_string($content) ? $content : null;
    }

    /**
     * Convert the recorded request to an array for debugging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'messages' => array_map(fn (Message $m) => $m->toArray(), $this->messages),
            'system' => $this->system,
            'maxTokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'topP' => $this->topP,
            'stopSequences' => $this->stopSequences,
            'tools' => $this->tools !== null ? array_map(fn (Tool $t) => $t->name, $this->tools) : null,
            'toolChoice' => $this->toolChoice instanceof Tool ? $this->toolChoice->name : $this->toolChoice,
            'schema' => $this->schema?->toJsonSchema(),
            'extraParams' => $this->extraParams,
            'timestamp' => $this->timestamp,
        ];
    }
}
