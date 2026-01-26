<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of an image generation request.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class ImageResult
{
    /**
     * @param ImageData[] $images The generated images.
     * @param array<string, mixed> $raw The raw response from the provider.
     */
    public function __construct(
        public array $images,
        public array $raw = [],
    ) {
    }
}
