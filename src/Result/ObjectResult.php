<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a structured object generation request.
 *
 * @template T
 */
final readonly class ObjectResult
{
    /**
     * @param T $object The generated object (parsed and hydrated from JSON).
     * @param string $text The raw JSON text from the model.
     * @param FinishReason|null $finishReason The reason generation stopped.
     * @param Usage|null $usage Token usage statistics.
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     */
    public function __construct(
        public mixed $object,
        public string $text,
        public ?FinishReason $finishReason = null,
        public ?Usage $usage = null,
        public array $rawResponse = [],
    ) {
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
