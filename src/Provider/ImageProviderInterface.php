<?php

namespace SageGrids\PhpAiSdk\Provider;

use SageGrids\PhpAiSdk\Result\ImageResult;

/**
 * Interface for providers that support image generation.
 */
interface ImageProviderInterface extends ProviderInterface
{
    /**
     * Generate an image from a text prompt.
     *
     * @param string $prompt The text description of the image to generate.
     * @param string|null $model The model to use for generation.
     * @param string $size Image size (e.g., '1024x1024', '512x512').
     * @param string $quality Image quality ('standard', 'hd').
     * @param string $style Image style ('vivid', 'natural').
     * @param int $n Number of images to generate.
     * @param string $responseFormat Response format ('url', 'b64_json').
     */
    public function generateImage(
        string $prompt,
        ?string $model = null,
        string $size = '1024x1024',
        string $quality = 'standard',
        string $style = 'vivid',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult;

    /**
     * Edit an existing image based on a prompt.
     *
     * @param string $image Path or base64 encoded image to edit.
     * @param string $prompt The text description of the desired edit.
     * @param string|null $mask Optional mask image for inpainting.
     * @param string|null $model The model to use for editing.
     * @param string $size Output image size.
     * @param int $n Number of images to generate.
     * @param string $responseFormat Response format ('url', 'b64_json').
     */
    public function editImage(
        string $image,
        string $prompt,
        ?string $mask = null,
        ?string $model = null,
        string $size = '1024x1024',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult;

    /**
     * Create variations of an existing image.
     *
     * @param string $image Path or base64 encoded image.
     * @param string|null $model The model to use.
     * @param string $size Output image size.
     * @param int $n Number of variations to generate.
     * @param string $responseFormat Response format ('url', 'b64_json').
     */
    public function createImageVariation(
        string $image,
        ?string $model = null,
        string $size = '1024x1024',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult;
}
