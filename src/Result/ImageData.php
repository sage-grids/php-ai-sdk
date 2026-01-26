<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Data for a single generated image.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class ImageData
{
    /**
     * @param string|null $url The URL of the generated image.
     * @param string|null $base64 The base64 encoded image data.
     * @param string|null $revisedPrompt The revised prompt used for generation (if available).
     */
    public function __construct(
        public ?string $url = null,
        public ?string $base64 = null,
        public ?string $revisedPrompt = null,
    ) {
    }
}
