<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Token usage statistics from an API call.
 */
final readonly class Usage
{
    /**
     * @param int $promptTokens Number of tokens in the prompt.
     * @param int $completionTokens Number of tokens in the completion.
     * @param int $totalTokens Total tokens used (prompt + completion).
     */
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
    ) {
    }

    /**
     * Create from a provider's usage response array.
     *
     * @param array<string, mixed> $data Provider response data.
     */
    public static function fromArray(array $data): self
    {
        /** @var int|string|null $promptRaw */
        $promptRaw = $data['prompt_tokens'] ?? $data['input_tokens'] ?? 0;
        /** @var int|string|null $completionRaw */
        $completionRaw = $data['completion_tokens'] ?? $data['output_tokens'] ?? 0;
        /** @var int|string|null $totalRaw */
        $totalRaw = $data['total_tokens'] ?? null;

        $promptTokens = (int) $promptRaw;
        $completionTokens = (int) $completionRaw;
        $totalTokens = $totalRaw !== null ? (int) $totalRaw : ($promptTokens + $completionTokens);

        return new self(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
        );
    }

    /**
     * Create a zero-usage instance.
     */
    public static function zero(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * Add another usage instance to this one.
     */
    public function add(Usage $other): self
    {
        return new self(
            promptTokens: $this->promptTokens + $other->promptTokens,
            completionTokens: $this->completionTokens + $other->completionTokens,
            totalTokens: $this->totalTokens + $other->totalTokens,
        );
    }

    /**
     * Convert to array format.
     *
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
