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
     * @param Usage|null $usage Token usage statistics (accumulated across all roundtrips).
     * @param ToolCall[] $toolCalls Any tool calls made by the model.
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     * @param Usage[] $roundtripUsage Per-roundtrip token usage (when tool calls were executed).
     */
    public function __construct(
        public string $text,
        public ?FinishReason $finishReason = null,
        public ?Usage $usage = null,
        public array $toolCalls = [],
        public array $rawResponse = [],
        public array $roundtripUsage = [],
    ) {
    }

    /**
     * Create a new TextResult with accumulated usage from tool roundtrips.
     *
     * @param Usage[] $roundtripUsage Per-roundtrip usage to accumulate.
     */
    public function withAccumulatedUsage(array $roundtripUsage): self
    {
        if (empty($roundtripUsage)) {
            return $this;
        }

        // Include current usage in the roundtrip list
        $allUsage = $roundtripUsage;
        if ($this->usage !== null) {
            $allUsage[] = $this->usage;
        }

        // Accumulate all usage
        $totalUsage = Usage::zero();
        foreach ($allUsage as $usage) {
            $totalUsage = $totalUsage->add($usage);
        }

        return new self(
            text: $this->text,
            finishReason: $this->finishReason,
            usage: $totalUsage,
            toolCalls: $this->toolCalls,
            rawResponse: $this->rawResponse,
            roundtripUsage: $allUsage,
        );
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
