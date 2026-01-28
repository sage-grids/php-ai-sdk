<?php

/**
 * Class-Based Schema Example
 *
 * This example demonstrates using PHP classes as schemas for structured output.
 *
 * Run: php docs/examples/class-based-schema.php
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
// Define DTOs (Data Transfer Objects)
// ============================================================================

/**
 * Simple user profile DTO.
 */
class UserProfile
{
    public string $name;
    public string $email;
    public int $age;
}

/**
 * Address DTO with optional fields.
 */
class Address
{
    public string $street;
    public string $city;
    public string $country;
    public ?string $postalCode = null;
}

/**
 * Product DTO with various types.
 */
class Product
{
    public string $name;
    public string $description;
    public float $price;
    public int $stockQuantity;
    public bool $isAvailable;
    /** @var string[] */
    public array $tags;
}

/**
 * Complex order DTO with nested objects.
 */
class OrderItem
{
    public string $productId;
    public string $productName;
    public int $quantity;
    public float $unitPrice;
}

class Order
{
    public string $orderId;
    public string $customerName;
    public string $status;
    /** @var OrderItem[] */
    public array $items;
    public float $totalAmount;
}

// ============================================================================
// Example 1: Simple class as schema
// ============================================================================

echo "Example 1: Simple class as schema\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a user profile for a software developer named John.',
    'schema' => UserProfile::class,
]);

echo "Name: {$result->object['name']}\n";
echo "Email: {$result->object['email']}\n";
echo "Age: {$result->object['age']}\n\n";

// ============================================================================
// Example 2: Class with optional fields
// ============================================================================

echo "Example 2: Class with optional fields\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a random address in Tokyo, Japan.',
    'schema' => Address::class,
]);

echo "Street: {$result->object['street']}\n";
echo "City: {$result->object['city']}\n";
echo "Country: {$result->object['country']}\n";
echo "Postal Code: " . ($result->object['postalCode'] ?? 'N/A') . "\n\n";

// ============================================================================
// Example 3: Class with arrays
// ============================================================================

echo "Example 3: Class with arrays\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a product listing for a wireless keyboard.',
    'schema' => Product::class,
]);

echo "Product: {$result->object['name']}\n";
echo "Description: {$result->object['description']}\n";
echo "Price: \${$result->object['price']}\n";
echo "In Stock: {$result->object['stockQuantity']} units\n";
echo "Available: " . ($result->object['isAvailable'] ? 'Yes' : 'No') . "\n";
echo "Tags: " . implode(', ', $result->object['tags']) . "\n\n";

// ============================================================================
// Example 4: Combining class schema with Schema builder
// ============================================================================

echo "Example 4: Hybrid schema approach\n";
echo str_repeat('-', 40) . "\n";

// Sometimes you need more control than a class provides
$reviewSchema = Schema::object([
    'reviewer' => Schema::string()->description('Name of the reviewer'),
    'rating' => Schema::integer()->description('Rating from 1 to 5'),
    'title' => Schema::string()->description('Review title'),
    'content' => Schema::string()->description('Review content'),
    'pros' => Schema::array(Schema::string())->description('List of positive points'),
    'cons' => Schema::array(Schema::string())->description('List of negative points'),
    'verified' => Schema::boolean()->description('Whether this is a verified purchase'),
    'helpfulVotes' => Schema::integer()->default(0),
]);

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a detailed product review for a laptop.',
    'schema' => $reviewSchema,
    'schemaName' => 'ProductReview',
    'schemaDescription' => 'A customer review for an e-commerce product',
]);

echo "Review by: {$result->object['reviewer']}\n";
echo "Rating: " . str_repeat('*', $result->object['rating']) . " ({$result->object['rating']}/5)\n";
echo "Title: {$result->object['title']}\n";
echo "Content: {$result->object['content']}\n";
echo "Pros:\n";
foreach ($result->object['pros'] as $pro) {
    echo "  + {$pro}\n";
}
echo "Cons:\n";
foreach ($result->object['cons'] as $con) {
    echo "  - {$con}\n";
}
echo "Verified: " . ($result->object['verified'] ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// Example 5: Generating multiple items
// ============================================================================

echo "Example 5: Generating a list\n";
echo str_repeat('-', 40) . "\n";

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate 3 different user profiles for a social network.',
    'schema' => Schema::object([
        'profiles' => Schema::array(
            Schema::object([
                'name' => Schema::string(),
                'email' => Schema::string(),
                'age' => Schema::integer(),
                'bio' => Schema::string()->description('Short biography'),
            ]),
        )->description('List of user profiles'),
    ]),
]);

foreach ($result->object['profiles'] as $index => $profile) {
    echo ($index + 1) . ". {$profile['name']} (Age: {$profile['age']})\n";
    echo "   Email: {$profile['email']}\n";
    echo "   Bio: {$profile['bio']}\n";
}
