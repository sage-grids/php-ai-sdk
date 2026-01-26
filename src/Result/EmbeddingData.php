<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Data for a single embedding.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class EmbeddingData
{
    /**
     * @param int $index The index of the input text this embedding corresponds to.
     * @param float[]|string $embedding The embedding vector (array of floats or base64 string).
     */
    public function __construct(
        public int $index,
        public array|string $embedding,
    ) {
    }
}
