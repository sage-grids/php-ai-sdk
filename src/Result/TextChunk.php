<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * A chunk of text from a streaming response.
 */
final readonly class TextChunk
{
    /**
     * @param string $text The accumulated text content so far.
     * @param string $delta The new text content in this chunk.
     * @param bool $isComplete Whether this is the final chunk.
     * @param FinishReason|null $finishReason The reason generation stopped (only set when isComplete).
     * @param Usage|null $usage Token usage statistics (only set when isComplete).
     */
    public function __construct(
        public string $text,
        public string $delta,
        public bool $isComplete = false,
        public ?FinishReason $finishReason = null,
        public ?Usage $usage = null,
    ) {
    }

    /**
     * Create the first chunk of a stream.
     */
    public static function first(string $delta): self
    {
        return new self(
            text: $delta,
            delta: $delta,
            isComplete: false,
        );
    }

    /**
     * Create a continuation chunk.
     */
    public static function continue(string $accumulatedText, string $delta): self
    {
        return new self(
            text: $accumulatedText,
            delta: $delta,
            isComplete: false,
        );
    }

    /**
     * Create the final chunk of a stream.
     */
    public static function final(
        string $accumulatedText,
        string $delta,
        ?FinishReason $finishReason = null,
        ?Usage $usage = null,
    ): self {
        return new self(
            text: $accumulatedText,
            delta: $delta,
            isComplete: true,
            finishReason: $finishReason,
            usage: $usage,
        );
    }
}
