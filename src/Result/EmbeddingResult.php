<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of an embedding generation request.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class EmbeddingResult
{
    /**
     * @param EmbeddingData[] $embeddings The generated embeddings.
     * @param string $model The model used for embeddings.
     * @param Usage|null $usage Token usage statistics.
     * @param array<string, mixed> $raw The raw response from the provider.
     */
    public function __construct(
        public array $embeddings,
        public string $model,
        public ?Usage $usage = null,
        public array $raw = [],
    ) {
    }
}
