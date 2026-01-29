<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of an image generation request.
 */
final readonly class ImageResult
{
    /**
     * @param ImageData[] $images The generated images.
     * @param Usage|null $usage Token/cost usage statistics (if available from provider).
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     */
    public function __construct(
        public array $images,
        public ?Usage $usage = null,
        public array $rawResponse = [],
    ) {
    }

    /**
     * Get the first (or only) generated image.
     */
    public function first(): ?ImageData
    {
        return $this->images[0] ?? null;
    }

    /**
     * Get the number of generated images.
     */
    public function count(): int
    {
        return count($this->images);
    }
}
