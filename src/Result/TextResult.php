<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a text generation request.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class TextResult
{
    /**
     * @param string $text The generated text content.
     * @param string|null $finishReason The reason generation stopped ('stop', 'length', 'tool_calls', etc.).
     * @param Usage|null $usage Token usage statistics.
     * @param ToolCall[] $toolCalls Any tool calls made by the model.
     * @param array<string, mixed> $raw The raw response from the provider.
     */
    public function __construct(
        public string $text,
        public ?string $finishReason = null,
        public ?Usage $usage = null,
        public array $toolCalls = [],
        public array $raw = [],
    ) {
    }
}
