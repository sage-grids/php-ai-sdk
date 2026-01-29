<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * Data for a single embedding vector.
 */
final readonly class EmbeddingData
{
    /**
     * @param int $index The index of the input text this embedding corresponds to.
     * @param float[] $embedding The embedding vector (array of floats).
     */
    public function __construct(
        public int $index,
        public array $embedding,
    ) {
    }

    /**
     * Create from a provider's embedding response.
     *
     * @param array<string, mixed> $data Provider response data.
     */
    public static function fromArray(array $data): self
    {
        $embedding = $data['embedding'] ?? [];

        // Handle base64 encoded embeddings (OpenAI with encoding_format=base64)
        if (is_string($embedding)) {
            $decoded = base64_decode($embedding);
            $unpacked = unpack('f*', $decoded);
            $embedding = $unpacked !== false ? array_values($unpacked) : [];
        }

        /** @var int|string|null $index */
        $index = $data['index'] ?? 0;

        /** @var float[] $floatEmbedding */
        $floatEmbedding = is_array($embedding) ? $embedding : [];

        return new self(
            index: (int) $index,
            embedding: $floatEmbedding,
        );
    }

    /**
     * Get the dimension of the embedding vector.
     */
    public function getDimension(): int
    {
        return count($this->embedding);
    }

    /**
     * Calculate cosine similarity with another embedding.
     */
    public function cosineSimilarity(self $other): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = min(count($this->embedding), count($other->embedding));

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $this->embedding[$i] * $other->embedding[$i];
            $normA += $this->embedding[$i] ** 2;
            $normB += $other->embedding[$i] ** 2;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }
}
