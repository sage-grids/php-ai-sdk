# PHP AI SDK

A unified, developer-friendly PHP SDK for interacting with multiple AI providers. Inspired by the [Vercel AI SDK](https://ai-sdk.dev), this library provides a consistent API for text generation, streaming, structured output, tool calling, embeddings, image generation, and speech synthesis.

## Installation

```bash
composer require sage-grids/php-ai-sdk
```

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client (automatically installed)

## Quick Start

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;

// Create a provider
$provider = new OpenAIProvider(
    new OpenAIConfig(apiKey: 'your-api-key'),
);

// Generate text
$result = AI::generateText([
    'model' => $provider,
    'prompt' => 'What is the meaning of life?',
]);

echo $result->text;
```

## Features

- **Unified API**: Single interface for multiple AI providers
- **Text Generation**: Generate text with customizable parameters
- **Streaming**: Real-time streaming of responses
- **Structured Output**: Generate type-safe JSON objects with schema validation
- **Tool Calling**: Let AI models call your functions
- **Embeddings**: Generate vector embeddings for text
- **Image Generation**: Create images from text prompts
- **Speech**: Text-to-speech and speech-to-text capabilities
- **Testing Utilities**: FakeProvider for unit testing without API calls

## Usage

### Text Generation

Generate text from a simple prompt:

```php
use SageGrids\PhpAiSdk\AI;

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Explain quantum computing in simple terms.',
    'maxTokens' => 500,
    'temperature' => 0.7,
]);

echo $result->text;
echo "Tokens used: " . $result->usage->totalTokens;
```

Using conversation messages:

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'system' => 'You are a helpful assistant.',
    'messages' => [
        new UserMessage('What is 2+2?'),
        new AssistantMessage('2+2 equals 4.'),
        new UserMessage('And what is that multiplied by 3?'),
    ],
]);
```

### Streaming Text

Stream responses in real-time:

```php
use SageGrids\PhpAiSdk\AI;

foreach (AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Write a short story about a robot.',
]) as $chunk) {
    echo $chunk->delta; // Print each chunk as it arrives
    flush();
}
```

With callbacks:

```php
$generator = AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Tell me a joke.',
    'onChunk' => function ($chunk) {
        // Called for each chunk
        echo $chunk->delta;
    },
    'onFinish' => function ($finalChunk) {
        // Called when streaming completes
        echo "\n\nTotal tokens: " . $finalChunk->usage?->totalTokens;
    },
]);

// Consume the generator
iterator_to_array($generator);
```

### Structured Output

Generate validated JSON objects using schemas:

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Schema\Schema;

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a user profile for John Doe',
    'schema' => Schema::object([
        'name' => Schema::string()->description('Full name'),
        'age' => Schema::integer()->description('Age in years'),
        'email' => Schema::string()->description('Email address'),
        'interests' => Schema::array(Schema::string())->description('List of hobbies'),
    ]),
]);

// $result->object is validated against the schema
echo $result->object['name'];     // "John Doe"
echo $result->object['age'];      // 30
print_r($result->object['interests']); // ["reading", "coding"]
```

Using PHP classes for schemas:

```php
class UserProfile
{
    public string $name;
    public int $age;
    public string $email;
    /** @var string[] */
    public array $interests;
}

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a user profile',
    'schema' => UserProfile::class,
]);
```

### Tool Calling

Let AI models call your functions:

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;

$weatherTool = Tool::create(
    name: 'get_weather',
    description: 'Get the current weather for a location',
    parameters: Schema::object([
        'city' => Schema::string()->description('City name'),
        'unit' => Schema::string()
            ->enum(['celsius', 'fahrenheit'])
            ->default('celsius'),
    ]),
    execute: function (array $args) {
        // Your weather API logic here
        return json_encode([
            'city' => $args['city'],
            'temperature' => 22,
            'unit' => $args['unit'],
            'condition' => 'sunny',
        ]);
    },
);

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the weather in Paris?',
    'tools' => [$weatherTool],
]);

echo $result->text; // "The weather in Paris is 22°C and sunny."
```

### Embeddings

Generate vector embeddings for semantic search:

```php
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;

$provider = new OpenAIProvider($config);

// Single text
$result = $provider->embed('Hello, world!');
$vector = $result->first()->embedding; // float[]

// Multiple texts
$result = $provider->embed([
    'The quick brown fox',
    'jumps over the lazy dog',
]);

// Calculate similarity
$similarity = $result->get(0)->cosineSimilarity($result->get(1));
```

### Image Generation

Create images from text prompts:

```php
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;

$provider = new OpenAIProvider($config);

$result = $provider->generateImage(
    prompt: 'A serene mountain landscape at sunset',
    size: '1024x1024',
    quality: 'hd',
);

$imageUrl = $result->first()->url;
```

### Speech

Text-to-speech:

```php
$result = $provider->generateSpeech(
    text: 'Hello, welcome to our application!',
    voice: 'alloy',
    responseFormat: 'mp3',
);

$result->saveTo('welcome.mp3');
```

Speech-to-text:

```php
$result = $provider->transcribe(
    audio: '/path/to/audio.mp3',
    language: 'en',
);

echo $result->text;
```

## Provider Setup

### OpenAI

```php
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;

$provider = new OpenAIProvider(
    new OpenAIConfig(
        apiKey: getenv('OPENAI_API_KEY'),
        organization: 'org-xxx', // Optional
        defaultModel: 'gpt-4o',
    ),
);
```

### Using Model Strings

Register providers and use model strings:

```php
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\AI;

// Register the provider
ProviderRegistry::getInstance()->register('openai', $provider);

// Use with model string
$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Hello!',
]);
```

### Global Configuration

Set defaults for all AI calls:

```php
use SageGrids\PhpAiSdk\AIConfig;

// Set default provider
AIConfig::setProvider('openai/gpt-4o');

// Set default parameters
AIConfig::setDefaults([
    'temperature' => 0.7,
    'maxTokens' => 1000,
]);

// Now you can omit the model parameter
$result = AI::generateText([
    'prompt' => 'Hello!',
]);
```

## Testing

The SDK includes a `FakeProvider` for testing without making real API calls:

```php
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Testing\AITestCase;
use SageGrids\PhpAiSdk\Testing\FakeProvider;
use SageGrids\PhpAiSdk\Testing\FakeResponse;
use SageGrids\PhpAiSdk\AI;

class MyFeatureTest extends TestCase
{
    use AITestCase;

    protected function setUp(): void
    {
        $this->setUpAI();
    }

    protected function tearDown(): void
    {
        $this->tearDownAI();
    }

    public function testGeneratesGreeting(): void
    {
        // Create fake provider with queued response
        $fake = $this->fake();
        $fake->addTextResponse('Hello, John!');

        // Call your code
        $result = AI::generateText([
            'model' => $fake,
            'prompt' => 'Greet John',
        ]);

        // Assert the response
        $this->assertAIGenerated($result, 'Hello, John!');

        // Assert the request was made correctly
        $this->assertAIRequestMade($fake, 'generateText');
        $this->assertAIRequestContains($fake, 'John');
    }

    public function testToolCalling(): void
    {
        $fake = $this->fake();

        // Queue a response with tool calls
        $fake->addResponse('generateText', FakeResponse::toolCalls([
            FakeResponse::toolCall('get_weather', ['city' => 'Paris']),
        ]));

        // Then queue the final response
        $fake->addTextResponse('The weather in Paris is sunny.');

        $result = AI::generateText([
            'model' => $fake,
            'prompt' => 'What is the weather in Paris?',
            'tools' => [$this->createWeatherTool()],
        ]);

        $this->assertAIGenerated($result, 'The weather in Paris is sunny.');
    }

    public function testStreamingResponse(): void
    {
        $fake = $this->fake();
        $fake->addTextStreamResponse(['Hello', ', ', 'world', '!']);

        $chunks = [];
        foreach (AI::streamText([
            'model' => $fake,
            'prompt' => 'Say hello',
        ]) as $chunk) {
            $chunks[] = $chunk->delta;
        }

        $this->assertEquals(['Hello', ', ', 'world', '!'], $chunks);
    }
}
```

### Using FakeResponse Helpers

```php
use SageGrids\PhpAiSdk\Testing\FakeResponse;

// Text responses
FakeResponse::text('Hello!');
FakeResponse::text('Hello!', FakeResponse::usage(10, 5));

// Tool calls
FakeResponse::toolCalls([
    FakeResponse::toolCall('get_weather', ['city' => 'Paris']),
]);

// Streaming chunks
FakeResponse::streamedText(['Hello', ' ', 'world']);

// Structured objects
FakeResponse::object(['name' => 'John', 'age' => 30]);
FakeResponse::streamedObject(
    finalObject: ['name' => 'John'],
    partialObjects: [['name' => 'Jo']],
);

// Embeddings
FakeResponse::embedding([0.1, 0.2, 0.3]);
FakeResponse::randomEmbedding(dimensions: 1536);

// Images
FakeResponse::image(url: 'https://example.com/image.png');

// Speech and transcription
FakeResponse::speech('audio-content');
FakeResponse::transcription('Hello, world!');
```

## Error Handling

The SDK provides specific exceptions for different error types:

```php
use SageGrids\PhpAiSdk\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Exception\RateLimitException;
use SageGrids\PhpAiSdk\Exception\InputValidationException;
use SageGrids\PhpAiSdk\Exception\ProviderException;

try {
    $result = AI::generateText([
        'model' => 'openai/gpt-4o',
        'prompt' => 'Hello',
    ]);
} catch (AuthenticationException $e) {
    // Invalid API key
} catch (RateLimitException $e) {
    // Rate limit exceeded - check $e->getRetryAfter()
} catch (InputValidationException $e) {
    // Invalid input parameters
} catch (ProviderException $e) {
    // Other provider errors
}
```

## API Reference

### AI Facade Methods

| Method | Description |
|--------|-------------|
| `AI::generateText($options)` | Generate text synchronously |
| `AI::streamText($options)` | Stream text generation |
| `AI::generateObject($options)` | Generate structured object |
| `AI::streamObject($options)` | Stream object generation |

### Common Options

| Option | Type | Description |
|--------|------|-------------|
| `model` | `string\|ProviderInterface` | Model identifier or provider instance |
| `prompt` | `string` | Simple text prompt |
| `messages` | `Message[]` | Conversation messages |
| `system` | `string` | System message |
| `maxTokens` | `int` | Maximum tokens to generate |
| `temperature` | `float` | Sampling temperature (0-2) |
| `topP` | `float` | Top-p sampling parameter |
| `stopSequences` | `string[]` | Sequences that stop generation |
| `tools` | `Tool[]` | Available tools |
| `toolChoice` | `string\|Tool` | Tool selection strategy |

### Schema Types

| Method | Description |
|--------|-------------|
| `Schema::string()` | String value |
| `Schema::integer()` | Integer value |
| `Schema::number()` | Floating-point number |
| `Schema::boolean()` | Boolean value |
| `Schema::array($items)` | Array of items |
| `Schema::object($properties)` | Object with properties |

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/sage-grids/php-ai-sdk.git
cd php-ai-sdk
composer install
```

### Running Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

### Code Style

```bash
composer cs-fix
# or
./vendor/bin/php-cs-fixer fix
```

### Static Analysis

```bash
composer phpstan
# or
./vendor/bin/phpstan analyse
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
