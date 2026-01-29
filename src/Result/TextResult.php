<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a text generation request.
 */
final readonly class TextResult
{
    /**
     * @param string $text The generated text content.
     * @param FinishReason|null $finishReason The reason generation stopped.
     * @param Usage|null $usage Token usage statistics.
     * @param ToolCall[] $toolCalls Any tool calls made by the model.
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     */
    public function __construct(
        public string $text,
        public ?FinishReason $finishReason = null,
        public ?Usage $usage = null,
        public array $toolCalls = [],
        public array $rawResponse = [],
    ) {
    }

    /**
     * Check if the model requested tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Check if generation completed naturally.
     */
    public function isComplete(): bool
    {
        return $this->finishReason === FinishReason::Stop;
    }

    /**
     * Check if generation was truncated due to length.
     */
    public function isTruncated(): bool
    {
        return $this->finishReason === FinishReason::Length;
    }
}
