<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Data for a single generated image.
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

    /**
     * Create from a provider's image response.
     *
     * @param array<string, mixed> $data Provider response data.
     */
    public static function fromArray(array $data): self
    {
        $url = $data['url'] ?? null;
        $base64 = $data['b64_json'] ?? $data['base64'] ?? null;
        $revisedPrompt = $data['revised_prompt'] ?? null;

        return new self(
            url: is_string($url) ? $url : null,
            base64: is_string($base64) ? $base64 : null,
            revisedPrompt: is_string($revisedPrompt) ? $revisedPrompt : null,
        );
    }

    /**
     * Check if image data is available as URL.
     */
    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    /**
     * Check if image data is available as base64.
     */
    public function hasBase64(): bool
    {
        return $this->base64 !== null;
    }
}
