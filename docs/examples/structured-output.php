<?php

/**
 * Structured Output Example
 *
 * This example demonstrates generating validated JSON objects using schemas.
 *
 * Run: php docs/examples/structured-output.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
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
// Example 1: Simple object generation
// ============================================================================

echo "Example 1: Simple object generation\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate information about a random programming language.',
    'schema' => Schema::object([
        'name' => Schema::string()->description('Name of the programming language'),
        'year' => Schema::integer()->description('Year the language was created'),
        'paradigm' => Schema::string()->description('Primary programming paradigm'),
    ]),
]);

echo "Language: {$result->object['name']}\n";
echo "Year: {$result->object['year']}\n";
echo "Paradigm: {$result->object['paradigm']}\n\n";

// ============================================================================
// Example 2: Complex nested schema
// ============================================================================

echo "Example 2: Complex nested schema\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a sample user profile for an e-commerce application.',
    'schema' => Schema::object([
        'user' => Schema::object([
            'id' => Schema::integer(),
            'name' => Schema::string(),
            'email' => Schema::string(),
        ])->description('User information'),
        'preferences' => Schema::object([
            'newsletter' => Schema::boolean(),
            'theme' => Schema::string()->enum(['light', 'dark', 'auto']),
        ])->description('User preferences'),
        'recentOrders' => Schema::array(
            Schema::object([
                'orderId' => Schema::string(),
                'total' => Schema::number(),
                'items' => Schema::integer(),
            ]),
        )->description('Recent order summaries'),
    ]),
    'schemaName' => 'UserProfile',
    'schemaDescription' => 'Complete user profile with preferences and order history',
]);

echo "User: {$result->object['user']['name']} ({$result->object['user']['email']})\n";
echo "Theme preference: {$result->object['preferences']['theme']}\n";
echo "Recent orders:\n";
foreach ($result->object['recentOrders'] as $order) {
    echo "  - Order {$order['orderId']}: \${$order['total']} ({$order['items']} items)\n";
}
echo "\n";

// ============================================================================
// Example 3: Array of items
// ============================================================================

echo "Example 3: Array of items\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a list of 3 fictional books with their details.',
    'schema' => Schema::object([
        'books' => Schema::array(
            Schema::object([
                'title' => Schema::string(),
                'author' => Schema::string(),
                'year' => Schema::integer(),
                'genre' => Schema::string(),
                'rating' => Schema::number()->description('Rating from 1.0 to 5.0'),
            ]),
        ),
    ]),
]);

foreach ($result->object['books'] as $index => $book) {
    echo ($index + 1) . ". \"{$book['title']}\" by {$book['author']} ({$book['year']})\n";
    echo "   Genre: {$book['genre']}, Rating: {$book['rating']}/5.0\n";
}
echo "\n";

// ============================================================================
// Example 4: Schema with enums and defaults
// ============================================================================

echo "Example 4: Schema with enums\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Create a task for implementing user authentication.',
    'schema' => Schema::object([
        'title' => Schema::string()->description('Task title'),
        'description' => Schema::string()->description('Detailed description'),
        'priority' => Schema::string()->enum(['low', 'medium', 'high', 'critical']),
        'status' => Schema::string()->enum(['todo', 'in_progress', 'review', 'done']),
        'estimatedHours' => Schema::integer()->description('Estimated hours to complete'),
        'tags' => Schema::array(Schema::string()),
    ]),
]);

echo "Task: {$result->object['title']}\n";
echo "Priority: {$result->object['priority']}\n";
echo "Status: {$result->object['status']}\n";
echo "Estimate: {$result->object['estimatedHours']} hours\n";
echo "Tags: " . implode(', ', $result->object['tags']) . "\n\n";

// ============================================================================
// Example 5: Streaming object generation
// ============================================================================

echo "Example 5: Streaming object generation\n";
echo str_repeat('-', 40) . "\n";

$partialObjects = [];

foreach (AI::streamObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a weather forecast for tomorrow.',
    'schema' => Schema::object([
        'date' => Schema::string(),
        'temperature' => Schema::object([
            'high' => Schema::integer(),
            'low' => Schema::integer(),
            'unit' => Schema::string(),
        ]),
        'conditions' => Schema::string(),
        'precipitation' => Schema::integer()->description('Chance of precipitation as percentage'),
    ]),
]) as $chunk) {
    if ($chunk->delta !== null) {
        echo ".";
        flush();
    }

    if ($chunk->isComplete) {
        echo "\n\nFinal object:\n";
        print_r($chunk->delta);
    }
}
