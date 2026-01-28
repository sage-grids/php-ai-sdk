<?php

/**
 * Streaming Text Generation Example
 *
 * This example demonstrates real-time streaming of AI responses.
 *
 * Run: php docs/examples/streaming.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;

// Create and register the OpenAI provider
$provider = new OpenAIProvider(
    new OpenAIConfig(
        apiKey: getenv('OPENAI_API_KEY') ?: 'your-api-key-here',
    ),
);

ProviderRegistry::getInstance()->register('openai', $provider);

// ============================================================================
// Example 1: Basic streaming
// ============================================================================

echo "Example 1: Basic streaming\n";
echo str_repeat('-', 40) . "\n";

foreach (AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Count from 1 to 10, one number per line.',
]) as $chunk) {
    echo $chunk->delta;
    flush(); // Ensure output is sent immediately
}

echo "\n\n";

// ============================================================================
// Example 2: Streaming with callbacks
// ============================================================================

echo "Example 2: Streaming with callbacks\n";
echo str_repeat('-', 40) . "\n";

$tokenCount = 0;

$generator = AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Write a very short poem about AI.',
    'onChunk' => function ($chunk) use (&$tokenCount) {
        // Called for each chunk
        echo $chunk->delta;
        flush();
    },
    'onFinish' => function ($chunk) {
        // Called when streaming completes
        echo "\n\n--- Stream completed ---\n";
        echo "Finish reason: " . ($chunk->finishReason?->value ?? 'unknown') . "\n";
        echo "Total tokens: " . ($chunk->usage?->totalTokens ?? 'unknown') . "\n";
    },
]);

// Important: You must consume the generator for callbacks to fire
iterator_to_array($generator);

echo "\n";

// ============================================================================
// Example 3: Building accumulated text
// ============================================================================

echo "Example 3: Building accumulated text\n";
echo str_repeat('-', 40) . "\n";

$fullText = '';
$lastChunk = null;

foreach (AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What are 3 benefits of exercise? Be brief.',
]) as $chunk) {
    $fullText = $chunk->text; // Accumulated text
    $lastChunk = $chunk;

    // Show progress
    echo ".";
    flush();
}

echo "\n\nFull response:\n{$fullText}\n";

if ($lastChunk?->isComplete) {
    echo "\nGeneration completed successfully!\n";
}

// ============================================================================
// Example 4: Streaming with system message
// ============================================================================

echo "\nExample 4: Streaming with personality\n";
echo str_repeat('-', 40) . "\n";

foreach (AI::streamText([
    'model' => 'openai/gpt-4o',
    'system' => 'You are an enthusiastic sports commentator. Keep responses brief.',
    'prompt' => 'Describe what happens when someone scores a goal.',
]) as $chunk) {
    echo $chunk->delta;
    flush();
}

echo "\n";
