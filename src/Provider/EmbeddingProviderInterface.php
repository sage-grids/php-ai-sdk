<?php

namespace SageGrids\PhpAiSdk\Provider;

use SageGrids\PhpAiSdk\Result\EmbeddingResult;

/**
 * Interface for providers that support text embeddings.
 */
interface EmbeddingProviderInterface extends ProviderInterface
{
    /**
     * Generate embeddings for input text(s).
     *
     * @param string|string[] $input Text or array of texts to embed.
     * @param string|null $model The model to use for embeddings.
     * @param int|null $dimensions The number of dimensions for the output embeddings.
     * @param string $encodingFormat The format of the output embeddings ('float', 'base64').
     */
    public function embed(
        string|array $input,
        ?string $model = null,
        ?int $dimensions = null,
        string $encodingFormat = 'float',
    ): EmbeddingResult;
}
