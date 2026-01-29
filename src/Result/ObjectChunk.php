<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * A chunk of structured object data from a streaming response.
 *
 * @template T
 */
final readonly class ObjectChunk
{
    /**
     * @param T|null $delta The partial object parsed from the accumulated JSON (may be incomplete).
     * @param string $text The accumulated JSON string received so far.
     * @param bool $isComplete Whether this is the final chunk.
     * @param FinishReason|null $finishReason The reason generation stopped (only set when isComplete).
     * @param Usage|null $usage Token usage statistics (only set when isComplete).
     */
    public function __construct(
        public mixed $delta = null,
        public string $text = '',
        public bool $isComplete = false,
        public ?FinishReason $finishReason = null,
        public ?Usage $usage = null,
    ) {
    }

    /**
     * Create an intermediate chunk.
     *
     * @param T|null $delta The partial object.
     * @param string $accumulatedText The accumulated JSON text.
     * @return self<T>
     */
    public static function partial(mixed $delta, string $accumulatedText): self
    {
        return new self(
            delta: $delta,
            text: $accumulatedText,
            isComplete: false,
        );
    }

    /**
     * Create the final chunk.
     *
     * @param T $delta The complete object.
     * @param string $text The complete JSON text.
     * @param FinishReason|null $finishReason The reason generation stopped.
     * @param Usage|null $usage Token usage statistics.
     * @return self<T>
     */
    public static function final(
        mixed $delta,
        string $text,
        ?FinishReason $finishReason = null,
        ?Usage $usage = null,
    ): self {
        return new self(
            delta: $delta,
            text: $text,
            isComplete: true,
            finishReason: $finishReason,
            usage: $usage,
        );
    }
}
