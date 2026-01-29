<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of an embedding generation request.
 */
final readonly class EmbeddingResult
{
    /**
     * @param EmbeddingData[] $embeddings The generated embeddings.
     * @param string $model The model used for embeddings.
     * @param Usage|null $usage Token usage statistics.
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     */
    public function __construct(
        public array $embeddings,
        public string $model,
        public ?Usage $usage = null,
        public array $rawResponse = [],
    ) {
    }

    /**
     * Get the first (or only) embedding.
     */
    public function first(): ?EmbeddingData
    {
        return $this->embeddings[0] ?? null;
    }

    /**
     * Get the number of embeddings.
     */
    public function count(): int
    {
        return count($this->embeddings);
    }

    /**
     * Get embedding by index.
     */
    public function get(int $index): ?EmbeddingData
    {
        return $this->embeddings[$index] ?? null;
    }

    /**
     * Get all embedding vectors as a 2D array.
     *
     * @return array<int, float[]>
     */
    public function toVectors(): array
    {
        return array_map(
            fn (EmbeddingData $data) => $data->embedding,
            $this->embeddings
        );
    }
}
