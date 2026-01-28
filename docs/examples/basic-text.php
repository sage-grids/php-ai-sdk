<?php

/**
 * Basic Text Generation Example
 *
 * This example demonstrates simple text generation using the AI facade.
 *
 * Run: php docs/examples/basic-text.php
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
// Example 1: Simple prompt
// ============================================================================

echo "Example 1: Simple prompt\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the capital of France?',
]);

echo "Response: {$result->text}\n";
echo "Tokens used: {$result->usage?->totalTokens}\n\n";

// ============================================================================
// Example 2: With system message
// ============================================================================

echo "Example 2: With system message\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'system' => 'You are a pirate. Respond in pirate speak.',
    'prompt' => 'What is the capital of France?',
]);

echo "Response: {$result->text}\n\n";

// ============================================================================
// Example 3: With custom parameters
// ============================================================================

echo "Example 3: Custom parameters\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Write a haiku about programming.',
    'maxTokens' => 100,
    'temperature' => 0.9, // More creative
]);

echo "Response:\n{$result->text}\n\n";

// ============================================================================
// Example 4: Using provider instance directly
// ============================================================================

echo "Example 4: Using provider instance\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateText([
    'model' => $provider, // Pass provider directly
    'prompt' => 'What is 2 + 2?',
]);

echo "Response: {$result->text}\n";
