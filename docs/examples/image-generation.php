<?php

/**
 * Image Generation Example
 *
 * This example demonstrates AI image generation capabilities.
 *
 * Run: php docs/examples/image-generation.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;

// Create the OpenAI provider
$provider = new OpenAIProvider(
    new OpenAIConfig(
        apiKey: getenv('OPENAI_API_KEY') ?: 'your-api-key-here',
    ),
);

// ============================================================================
// Example 1: Basic image generation
// ============================================================================

echo "Example 1: Basic image generation\n";
echo str_repeat('-', 40) . "\n";

$result = $provider->generateImage(
    prompt: 'A serene mountain landscape at sunset with a calm lake in the foreground',
);

echo "Generated image URL: {$result->first()->url}\n";
if ($result->first()->revisedPrompt) {
    echo "Revised prompt: {$result->first()->revisedPrompt}\n";
}
echo "\n";

// ============================================================================
// Example 2: Image with custom size and quality
// ============================================================================

echo "Example 2: Custom size and quality\n";
echo str_repeat('-', 40) . "\n";

$result = $provider->generateImage(
    prompt: 'A futuristic city skyline with flying cars and neon lights',
    size: '1792x1024', // Wide format
    quality: 'hd',     // Higher quality
    style: 'vivid',    // More vivid colors
);

echo "Generated HD image: {$result->first()->url}\n\n";

// ============================================================================
// Example 3: Multiple images
// ============================================================================

echo "Example 3: Generate multiple images\n";
echo str_repeat('-', 40) . "\n";

$result = $provider->generateImage(
    prompt: 'A cute robot mascot for a tech company',
    n: 3, // Generate 3 variations
);

echo "Generated {$result->count()} images:\n";
foreach ($result->images as $index => $image) {
    echo "  " . ($index + 1) . ". {$image->url}\n";
}
echo "\n";

// ============================================================================
// Example 4: Base64 response format
// ============================================================================

echo "Example 4: Base64 response format\n";
echo str_repeat('-', 40) . "\n";

$result = $provider->generateImage(
    prompt: 'A simple geometric pattern in blue and white',
    size: '512x512',
    responseFormat: 'b64_json',
);

$image = $result->first();
if ($image->hasBase64()) {
    // In a real application, you could save this to a file:
    // file_put_contents('image.png', base64_decode($image->base64));
    $size = strlen($image->base64);
    echo "Received base64 image data ({$size} characters)\n";
    echo "First 50 chars: " . substr($image->base64, 0, 50) . "...\n";
} else {
    echo "Image URL: {$image->url}\n";
}
echo "\n";

// ============================================================================
// Example 5: Different styles
// ============================================================================

echo "Example 5: Different image styles\n";
echo str_repeat('-', 40) . "\n";

$prompt = 'A cozy coffee shop interior';

// Vivid style - more dramatic and colorful
$vividResult = $provider->generateImage(
    prompt: $prompt,
    style: 'vivid',
);
echo "Vivid style: {$vividResult->first()->url}\n";

// Natural style - more photorealistic
$naturalResult = $provider->generateImage(
    prompt: $prompt,
    style: 'natural',
);
echo "Natural style: {$naturalResult->first()->url}\n\n";

// ============================================================================
// Example 6: Programmatic prompt building
// ============================================================================

echo "Example 6: Programmatic prompts\n";
echo str_repeat('-', 40) . "\n";

/**
 * Build a detailed image prompt from components.
 *
 * @param string $subject Main subject
 * @param string $style Art style
 * @param string $mood Mood/atmosphere
 * @param string $lighting Lighting conditions
 * @return string
 */
function buildImagePrompt(
    string $subject,
    string $style = 'photorealistic',
    string $mood = 'peaceful',
    string $lighting = 'natural lighting',
): string {
    return "{$subject}, {$style} style, {$mood} mood, {$lighting}, highly detailed, professional photography";
}

$prompt = buildImagePrompt(
    subject: 'A golden retriever playing in autumn leaves',
    style: 'cinematic',
    mood: 'joyful',
    lighting: 'golden hour sunlight',
);

echo "Generated prompt: {$prompt}\n";

$result = $provider->generateImage(
    prompt: $prompt,
    quality: 'hd',
);

echo "Image URL: {$result->first()->url}\n";
