<?php

/**
 * Tool Calling Example
 *
 * This example demonstrates how to let AI models call your functions.
 *
 * Run: php docs/examples/tool-calling.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
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
// Example 1: Simple tool calling
// ============================================================================

echo "Example 1: Simple tool calling\n";
echo str_repeat('-', 40) . "\n";

// Define a weather tool
$weatherTool = Tool::create(
    name: 'get_weather',
    description: 'Get the current weather for a specific city',
    parameters: Schema::object([
        'city' => Schema::string()->description('The city name'),
        'unit' => Schema::string()
            ->enum(['celsius', 'fahrenheit'])
            ->default('celsius')
            ->description('Temperature unit'),
    ]),
    execute: function (array $args): string {
        // Simulate weather API response
        $city = $args['city'];
        $unit = $args['unit'] ?? 'celsius';

        $temp = rand(15, 30);
        if ($unit === 'fahrenheit') {
            $temp = (int) ($temp * 9 / 5 + 32);
        }

        return json_encode([
            'city' => $city,
            'temperature' => $temp,
            'unit' => $unit,
            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
            'humidity' => rand(40, 80) . '%',
        ]);
    },
);

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the weather in Paris?',
    'tools' => [$weatherTool],
]);

echo "Response: {$result->text}\n\n";

// ============================================================================
// Example 2: Multiple tools
// ============================================================================

echo "Example 2: Multiple tools\n";
echo str_repeat('-', 40) . "\n";

// Calculator tool
$calculatorTool = Tool::create(
    name: 'calculate',
    description: 'Perform mathematical calculations',
    parameters: Schema::object([
        'expression' => Schema::string()->description('Mathematical expression to evaluate'),
    ]),
    execute: function (array $args): string {
        $expression = $args['expression'];
        // Simple evaluation (in production, use a proper math parser)
        $result = @eval("return {$expression};");

        return json_encode([
            'expression' => $expression,
            'result' => $result ?? 'Error: Could not evaluate',
        ]);
    },
);

// Unit converter tool
$converterTool = Tool::create(
    name: 'convert_units',
    description: 'Convert between different units of measurement',
    parameters: Schema::object([
        'value' => Schema::number()->description('The value to convert'),
        'from' => Schema::string()->description('Source unit'),
        'to' => Schema::string()->description('Target unit'),
    ]),
    execute: function (array $args): string {
        $value = $args['value'];
        $from = strtolower($args['from']);
        $to = strtolower($args['to']);

        // Simple conversions
        $conversions = [
            'km_miles' => fn ($v) => $v * 0.621371,
            'miles_km' => fn ($v) => $v * 1.60934,
            'kg_lbs' => fn ($v) => $v * 2.20462,
            'lbs_kg' => fn ($v) => $v * 0.453592,
            'c_f' => fn ($v) => $v * 9 / 5 + 32,
            'f_c' => fn ($v) => ($v - 32) * 5 / 9,
        ];

        $key = "{$from}_{$to}";
        $result = isset($conversions[$key])
            ? round($conversions[$key]($value), 2)
            : 'Conversion not supported';

        return json_encode([
            'original' => "{$value} {$from}",
            'converted' => "{$result} {$to}",
        ]);
    },
);

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'If I have 100 kilometers, how many miles is that? Also calculate 100 * 1.60934.',
    'tools' => [$calculatorTool, $converterTool],
]);

echo "Response: {$result->text}\n\n";

// ============================================================================
// Example 3: Tool with complex parameters
// ============================================================================

echo "Example 3: Complex tool parameters\n";
echo str_repeat('-', 40) . "\n";

$searchTool = Tool::create(
    name: 'search_products',
    description: 'Search for products in the inventory',
    parameters: Schema::object([
        'query' => Schema::string()->description('Search query'),
        'filters' => Schema::object([
            'minPrice' => Schema::number()->optional()->description('Minimum price'),
            'maxPrice' => Schema::number()->optional()->description('Maximum price'),
            'category' => Schema::string()->optional()->description('Product category'),
            'inStock' => Schema::boolean()->default(true)->description('Only show in-stock items'),
        ])->optional(),
        'limit' => Schema::integer()->default(5)->description('Maximum results to return'),
    ]),
    execute: function (array $args): string {
        // Simulate product search
        $query = $args['query'];
        $filters = $args['filters'] ?? [];
        $limit = $args['limit'] ?? 5;

        $products = [
            ['name' => 'Wireless Headphones', 'price' => 79.99, 'category' => 'Electronics', 'inStock' => true],
            ['name' => 'Bluetooth Speaker', 'price' => 49.99, 'category' => 'Electronics', 'inStock' => true],
            ['name' => 'USB-C Cable', 'price' => 12.99, 'category' => 'Accessories', 'inStock' => true],
            ['name' => 'Laptop Stand', 'price' => 39.99, 'category' => 'Accessories', 'inStock' => false],
        ];

        // Apply filters
        $results = array_filter($products, function ($p) use ($query, $filters) {
            if (!str_contains(strtolower($p['name']), strtolower($query))) {
                return false;
            }
            if (isset($filters['minPrice']) && $p['price'] < $filters['minPrice']) {
                return false;
            }
            if (isset($filters['maxPrice']) && $p['price'] > $filters['maxPrice']) {
                return false;
            }
            if (isset($filters['category']) && $p['category'] !== $filters['category']) {
                return false;
            }
            if (($filters['inStock'] ?? true) && !$p['inStock']) {
                return false;
            }

            return true;
        });

        return json_encode([
            'query' => $query,
            'filters' => $filters,
            'results' => array_slice(array_values($results), 0, $limit),
            'totalFound' => count($results),
        ]);
    },
);

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Find electronics products under $60 that are in stock.',
    'tools' => [$searchTool],
]);

echo "Response: {$result->text}\n\n";

// ============================================================================
// Example 4: Controlling tool choice
// ============================================================================

echo "Example 4: Controlling tool choice\n";
echo str_repeat('-', 40) . "\n";

// Force the model to use a specific tool
$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Tell me about the current conditions.',
    'tools' => [$weatherTool],
    'toolChoice' => $weatherTool, // Force use of weather tool
]);

echo "Response (forced tool): {$result->text}\n\n";

// Prevent tool usage
$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the weather usually like in summer?',
    'tools' => [$weatherTool],
    'toolChoice' => 'none', // Don't use tools
]);

echo "Response (no tools): {$result->text}\n";
