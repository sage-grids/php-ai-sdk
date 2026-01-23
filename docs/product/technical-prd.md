# Technical PRD: sage-grids/php-ai-sdk

## Document Info

| Field | Value |
|-------|-------|
| Version | 1.0 |
| Status | Draft |
| Author | Engineering Team |
| Last Updated | 2026-01-24 |

---

## 1. Executive Summary

### 1.1 Purpose

`sage-grids/php-ai-sdk` is a PHP Composer package that provides a unified, developer-friendly interface for interacting with multiple AI providers. The architecture and API closely mirror the popular JavaScript AI-SDK (ai-sdk.dev), bringing the same excellent developer experience to PHP developers.

### 1.2 Core Value Proposition

- **Unified API**: One consistent interface across all AI providers
- **ai-sdk.dev Alignment**: Familiar patterns for developers who know the JavaScript AI-SDK
- **Framework-Agnostic**: Works with Laravel, Symfony, vanilla PHP, or any framework
- **Modern PHP**: Leverages PHP 8.1+ features (enums, named arguments, readonly properties, attributes)
- **First-Class Streaming**: Generator-based streaming with proper chunk handling

### 1.3 Target Users

1. PHP Backend Developers integrating AI into applications
2. Tech Leads evaluating AI solutions with vendor flexibility requirements
3. Agency developers needing quick AI integration for client projects
4. Enterprise teams requiring standardized AI usage patterns

---

## 2. Goals and Non-Goals

### 2.1 Goals

| Goal | Success Criteria |
|------|------------------|
| Unified provider interface | Switch providers with single line change |
| ai-sdk.dev API parity | Core functions match JS SDK naming/behavior |
| Framework independence | Zero framework dependencies in core package |
| Type safety | 100% PHPStan level 8 compatibility |
| Streaming support | Generator-based streaming for all text operations |
| Structured output | Schema-based object generation with validation |
| Tool calling | Clean abstractions for AI function/tool definitions |
| Testability | Mockable interfaces and testing utilities |

### 2.2 Non-Goals (for v1.0)

- Vector database / RAG support (Phase 2)
- Agent orchestration framework (Phase 2)
- MCP (Model Context Protocol) support (Phase 2)
- Framework-specific packages (Phase 3)
- DevTools / debugging UI (Phase 3)
- Image editing capabilities (Phase 2)

---

## 3. Architecture Overview

### 3.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Application Layer                             │
│                   (Laravel, Symfony, Vanilla PHP)                    │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         PHP AI SDK Core                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │ generateText│  │ streamText  │  │generateObject│ │streamObject │ │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘ │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │generateImage│  │generateSpeech│ │ transcribe  │  │   embed     │ │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Provider Abstraction Layer                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    ProviderInterface                             ││
│  │  - generateText(prompt, options): TextResult                     ││
│  │  - streamText(prompt, options): Generator<TextChunk>             ││
│  │  - generateObject(prompt, schema, options): ObjectResult         ││
│  │  - streamObject(prompt, schema, options): Generator<ObjectChunk> ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
                                   │
        ┌──────────────┬───────────┼───────────┬──────────────┐
        ▼              ▼           ▼           ▼              ▼
┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐
│   OpenAI     ││   Gemini     ││  OpenRouter  ││  ElevenLabs  ││    Tavily    │
│   Provider   ││   Provider   ││   Provider   ││   Provider   ││   Provider   │
└──────────────┘└──────────────┘└──────────────┘└──────────────┘└──────────────┘
        │              │           │           │              │
        ▼              ▼           ▼           ▼              ▼
┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐
│  OpenAI API  ││  Gemini API  ││ OpenRouter   ││ ElevenLabs   ││  Tavily API  │
│              ││              ││    API       ││     API      ││              │
└──────────────┘└──────────────┘└──────────────┘└──────────────┘└──────────────┘
```

### 3.2 Package Structure

```
sage-grids/php-ai-sdk/
├── src/
│   ├── AI.php                          # Main entry point / facade
│   ├── AIConfig.php                    # Global configuration
│   │
│   ├── Core/
│   │   ├── Functions/
│   │   │   ├── GenerateText.php        # generateText implementation
│   │   │   ├── StreamText.php          # streamText implementation
│   │   │   ├── GenerateObject.php      # generateObject implementation
│   │   │   ├── StreamObject.php        # streamObject implementation
│   │   │   ├── GenerateImage.php       # generateImage implementation
│   │   │   ├── GenerateSpeech.php      # generateSpeech implementation
│   │   │   ├── Transcribe.php          # transcribe implementation
│   │   │   └── Embed.php               # embed implementation
│   │   │
│   │   ├── Message/
│   │   │   ├── Message.php             # Base message class
│   │   │   ├── SystemMessage.php       # System message
│   │   │   ├── UserMessage.php         # User message
│   │   │   ├── AssistantMessage.php    # Assistant message
│   │   │   ├── ToolMessage.php         # Tool result message
│   │   │   └── MessageCollection.php   # Message list handling
│   │   │
│   │   ├── Tool/
│   │   │   ├── Tool.php                # Tool definition
│   │   │   ├── ToolCall.php            # Tool call representation
│   │   │   ├── ToolResult.php          # Tool execution result
│   │   │   ├── ToolExecutor.php        # Tool execution engine
│   │   │   └── ToolRegistry.php        # Tool registration and lookup
│   │   │
│   │   ├── Schema/
│   │   │   ├── Schema.php              # Base schema class
│   │   │   ├── ObjectSchema.php        # Object schema definition
│   │   │   ├── ArraySchema.php         # Array schema definition
│   │   │   ├── StringSchema.php        # String schema with constraints
│   │   │   ├── NumberSchema.php        # Number schema with constraints
│   │   │   ├── BooleanSchema.php       # Boolean schema
│   │   │   ├── EnumSchema.php          # Enum schema
│   │   │   └── SchemaValidator.php     # Schema validation
│   │   │
│   │   └── Result/
│   │       ├── TextResult.php          # Text generation result
│   │       ├── TextChunk.php           # Streaming text chunk
│   │       ├── ObjectResult.php        # Object generation result
│   │       ├── ObjectChunk.php         # Streaming object chunk
│   │       ├── ImageResult.php         # Image generation result
│   │       ├── SpeechResult.php        # Speech generation result
│   │       ├── TranscriptionResult.php # Transcription result
│   │       ├── EmbeddingResult.php     # Embedding result
│   │       └── Usage.php               # Token/cost usage tracking
│   │
│   ├── Provider/
│   │   ├── ProviderInterface.php       # Main provider contract
│   │   ├── TextProviderInterface.php   # Text generation contract
│   │   ├── ImageProviderInterface.php  # Image generation contract
│   │   ├── SpeechProviderInterface.php # Speech generation contract
│   │   ├── EmbeddingProviderInterface.php # Embedding contract
│   │   ├── ProviderRegistry.php        # Provider registration
│   │   ├── ProviderConfig.php          # Provider configuration
│   │   │
│   │   ├── OpenAI/
│   │   │   ├── OpenAIProvider.php      # OpenAI implementation
│   │   │   ├── OpenAIConfig.php        # OpenAI-specific config
│   │   │   └── OpenAIModels.php        # Available models enum
│   │   │
│   │   ├── Gemini/
│   │   │   ├── GeminiProvider.php      # Gemini implementation
│   │   │   ├── GeminiConfig.php        # Gemini-specific config
│   │   │   └── GeminiModels.php        # Available models enum
│   │   │
│   │   ├── OpenRouter/
│   │   │   ├── OpenRouterProvider.php  # OpenRouter implementation
│   │   │   ├── OpenRouterConfig.php    # OpenRouter-specific config
│   │   │   └── OpenRouterModels.php    # Available models enum
│   │   │
│   │   ├── ElevenLabs/
│   │   │   ├── ElevenLabsProvider.php  # ElevenLabs implementation
│   │   │   ├── ElevenLabsConfig.php    # ElevenLabs-specific config
│   │   │   └── ElevenLabsVoices.php    # Available voices enum
│   │   │
│   │   └── Tavily/
│   │       ├── TavilyProvider.php      # Tavily search implementation
│   │       └── TavilyConfig.php        # Tavily-specific config
│   │
│   ├── Http/
│   │   ├── HttpClientInterface.php     # HTTP client contract
│   │   ├── GuzzleHttpClient.php        # Guzzle implementation
│   │   ├── StreamingResponse.php       # SSE stream handling
│   │   ├── Request.php                 # HTTP request abstraction
│   │   └── Response.php                # HTTP response abstraction
│   │
│   ├── Exception/
│   │   ├── AIException.php             # Base exception
│   │   ├── ProviderException.php       # Provider-specific errors
│   │   ├── RateLimitException.php      # Rate limit errors
│   │   ├── AuthenticationException.php # Auth errors
│   │   ├── ValidationException.php     # Schema validation errors
│   │   ├── TimeoutException.php        # Timeout errors
│   │   └── ToolExecutionException.php  # Tool execution errors
│   │
│   ├── Event/
│   │   ├── EventDispatcherInterface.php # Event dispatcher contract
│   │   ├── NullEventDispatcher.php     # No-op dispatcher
│   │   ├── Events/
│   │   │   ├── RequestStarted.php      # Before API request
│   │   │   ├── RequestCompleted.php    # After API request
│   │   │   ├── StreamChunkReceived.php # On each stream chunk
│   │   │   ├── ToolCallStarted.php     # Before tool execution
│   │   │   ├── ToolCallCompleted.php   # After tool execution
│   │   │   └── ErrorOccurred.php       # On any error
│   │   └── PSR14EventDispatcher.php    # PSR-14 adapter
│   │
│   └── Testing/
│       ├── FakeProvider.php            # Mock provider for testing
│       ├── FakeResponse.php            # Fake API responses
│       ├── RecordedRequest.php         # Request recording
│       └── AITestCase.php              # Test utilities trait
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
│
├── composer.json
├── phpstan.neon
├── phpunit.xml
└── README.md
```

---

## 4. Core API Design

### 4.1 Main Entry Point

The `AI` class serves as the primary entry point, providing static methods that mirror the JavaScript AI-SDK:

```php
<?php

namespace SageGrids\PhpAiSdk;

use SageGrids\PhpAiSdk\Core\Result\TextResult;
use SageGrids\PhpAiSdk\Core\Result\ObjectResult;
use SageGrids\PhpAiSdk\Core\Result\ImageResult;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;

final class AI
{
    /**
     * Generate text from a prompt.
     *
     * @param array{
     *     model: string|ProviderInterface,
     *     prompt?: string,
     *     messages?: array<Message>,
     *     system?: string,
     *     maxTokens?: int,
     *     temperature?: float,
     *     topP?: float,
     *     stopSequences?: array<string>,
     *     tools?: array<Tool>,
     *     toolChoice?: 'auto'|'none'|'required'|Tool,
     * } $options
     */
    public static function generateText(array $options): TextResult;

    /**
     * Stream text from a prompt.
     *
     * @return \Generator<TextChunk>
     */
    public static function streamText(array $options): \Generator;

    /**
     * Generate a structured object from a prompt.
     *
     * @template T
     * @param array{
     *     model: string|ProviderInterface,
     *     prompt?: string,
     *     messages?: array<Message>,
     *     system?: string,
     *     schema: Schema<T>|class-string<T>,
     *     schemaName?: string,
     *     schemaDescription?: string,
     *     maxTokens?: int,
     *     temperature?: float,
     * } $options
     * @return ObjectResult<T>
     */
    public static function generateObject(array $options): ObjectResult;

    /**
     * Stream a structured object from a prompt.
     *
     * @template T
     * @return \Generator<ObjectChunk<T>>
     */
    public static function streamObject(array $options): \Generator;

    /**
     * Generate an image from a prompt.
     */
    public static function generateImage(array $options): ImageResult;

    /**
     * Generate speech from text.
     */
    public static function generateSpeech(array $options): SpeechResult;

    /**
     * Transcribe audio to text.
     */
    public static function transcribe(array $options): TranscriptionResult;

    /**
     * Generate embeddings for text.
     */
    public static function embed(array $options): EmbeddingResult;
}
```

### 4.2 Usage Examples

#### Basic Text Generation

```php
use SageGrids\PhpAiSdk\AI;

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Write a haiku about PHP.',
]);

echo $result->text;
// Server scripts flow,
// Hypertext dreams come alive,
// PHP endures.

echo $result->usage->totalTokens; // 42
```

#### Streaming Text

```php
use SageGrids\PhpAiSdk\AI;

$stream = AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Explain quantum computing in simple terms.',
]);

foreach ($stream as $chunk) {
    echo $chunk->text;
    flush();
}

// Access final result after streaming completes
$result = $stream->getReturn();
echo $result->usage->totalTokens;
```

#### Structured Object Generation

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Schema\Schema;

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a recipe for chocolate chip cookies.',
    'schema' => Schema::object([
        'name' => Schema::string()->description('Recipe name'),
        'ingredients' => Schema::array(
            Schema::object([
                'item' => Schema::string(),
                'amount' => Schema::string(),
            ])
        ),
        'instructions' => Schema::array(Schema::string()),
        'prepTime' => Schema::number()->description('Prep time in minutes'),
        'cookTime' => Schema::number()->description('Cook time in minutes'),
    ]),
    'schemaName' => 'Recipe',
]);

$recipe = $result->object;
echo $recipe->name; // "Classic Chocolate Chip Cookies"
foreach ($recipe->ingredients as $ingredient) {
    echo "{$ingredient->amount} {$ingredient->item}\n";
}
```

#### Using PHP Classes as Schema

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Schema\Attributes\Description;

readonly class Recipe
{
    public function __construct(
        #[Description('The name of the recipe')]
        public string $name,

        /** @var Ingredient[] */
        #[Description('List of ingredients')]
        public array $ingredients,

        /** @var string[] */
        public array $instructions,

        #[Description('Preparation time in minutes')]
        public int $prepTime,

        #[Description('Cooking time in minutes')]
        public int $cookTime,
    ) {}
}

readonly class Ingredient
{
    public function __construct(
        public string $item,
        public string $amount,
    ) {}
}

$result = AI::generateObject([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Generate a recipe for chocolate chip cookies.',
    'schema' => Recipe::class,
]);

$recipe = $result->object; // Recipe instance
```

#### Tool/Function Calling

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Core\Schema\Schema;

$weatherTool = Tool::create(
    name: 'getWeather',
    description: 'Get the current weather for a location',
    parameters: Schema::object([
        'location' => Schema::string()->description('City name'),
        'unit' => Schema::enum(['celsius', 'fahrenheit'])->default('celsius'),
    ]),
    execute: function (array $params): array {
        // Call actual weather API
        return [
            'temperature' => 22,
            'unit' => $params['unit'],
            'condition' => 'sunny',
        ];
    }
);

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the weather in Paris?',
    'tools' => [$weatherTool],
    'toolChoice' => 'auto',
]);

// Tool calls are automatically executed
echo $result->text; // "The weather in Paris is sunny with a temperature of 22°C."

// Access tool call details
foreach ($result->toolCalls as $call) {
    echo "Called: {$call->name}\n";
    echo "Args: " . json_encode($call->arguments) . "\n";
    echo "Result: " . json_encode($call->result) . "\n";
}
```

#### Multi-Turn Conversations

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'messages' => [
        new SystemMessage('You are a helpful cooking assistant.'),
        new UserMessage('I want to make pasta.'),
        new AssistantMessage('Great choice! What type of pasta dish would you like?'),
        new UserMessage('Something with tomatoes and basil.'),
    ],
]);
```

#### Image Generation

```php
use SageGrids\PhpAiSdk\AI;

$result = AI::generateImage([
    'model' => 'openai/dall-e-3',
    'prompt' => 'A serene Japanese garden with a koi pond at sunset',
    'size' => '1024x1024',
    'quality' => 'hd',
]);

// Get image URL
echo $result->url;

// Or get base64 data
$imageData = $result->base64;
file_put_contents('garden.png', base64_decode($imageData));
```

#### Text-to-Speech

```php
use SageGrids\PhpAiSdk\AI;

$result = AI::generateSpeech([
    'model' => 'elevenlabs/eleven_multilingual_v2',
    'text' => 'Hello, welcome to our application!',
    'voice' => 'rachel',
]);

// Save audio file
file_put_contents('welcome.mp3', $result->audio);
```

#### Speech-to-Text

```php
use SageGrids\PhpAiSdk\AI;

$result = AI::transcribe([
    'model' => 'openai/whisper-1',
    'audio' => file_get_contents('recording.mp3'),
]);

echo $result->text;
echo $result->duration; // Duration in seconds
```

---

## 5. Provider System

### 5.1 Provider Interface

```php
<?php

namespace SageGrids\PhpAiSdk\Provider;

use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Result\TextResult;
use SageGrids\PhpAiSdk\Core\Result\TextChunk;
use SageGrids\PhpAiSdk\Core\Result\ObjectResult;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;

interface TextProviderInterface
{
    /**
     * Generate text completion.
     *
     * @param array<Message> $messages
     * @param array<Tool> $tools
     */
    public function generateText(
        array $messages,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): TextResult;

    /**
     * Stream text completion.
     *
     * @return \Generator<TextChunk>
     */
    public function streamText(
        array $messages,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): \Generator;

    /**
     * Generate structured object.
     *
     * @template T
     * @param Schema<T> $schema
     * @return ObjectResult<T>
     */
    public function generateObject(
        array $messages,
        Schema $schema,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ObjectResult;

    /**
     * Stream structured object.
     *
     * @template T
     * @return \Generator<ObjectChunk<T>>
     */
    public function streamObject(
        array $messages,
        Schema $schema,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): \Generator;
}
```

### 5.2 Model String Format

Following ai-sdk.dev conventions, models are specified as `provider/model`:

```php
// Provider/model format
'openai/gpt-4o'
'openai/gpt-4o-mini'
'gemini/gemini-1.5-pro'
'gemini/gemini-1.5-flash'
'openrouter/anthropic/claude-3.5-sonnet'
'elevenlabs/eleven_multilingual_v2'
```

The first segment determines the provider, the rest is the model identifier.

### 5.3 Provider Registration

```php
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\Gemini\GeminiProvider;

// Configure providers (typically in bootstrap)
AIConfig::setProvider('openai', new OpenAIProvider(
    apiKey: getenv('OPENAI_API_KEY'),
));

AIConfig::setProvider('gemini', new GeminiProvider(
    apiKey: getenv('GEMINI_API_KEY'),
));

// Or use auto-configuration from environment
AIConfig::autoConfigureFromEnv();
```

### 5.4 Provider Capabilities

Each provider declares its capabilities:

```php
interface ProviderInterface
{
    public function getName(): string;

    public function getCapabilities(): ProviderCapabilities;

    public function getAvailableModels(): array;
}

final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $supportsTextGeneration = false,
        public bool $supportsStreaming = false,
        public bool $supportsStructuredOutput = false,
        public bool $supportsToolCalling = false,
        public bool $supportsImageGeneration = false,
        public bool $supportsSpeechGeneration = false,
        public bool $supportsTranscription = false,
        public bool $supportsEmbeddings = false,
        public bool $supportsVision = false,
    ) {}
}
```

---

## 6. Schema System

### 6.1 Schema Definition API

The schema system provides a fluent API for defining structured output schemas:

```php
<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

abstract class Schema
{
    public static function string(): StringSchema;
    public static function number(): NumberSchema;
    public static function integer(): IntegerSchema;
    public static function boolean(): BooleanSchema;
    public static function array(Schema $items): ArraySchema;
    public static function object(array $properties): ObjectSchema;
    public static function enum(array $values): EnumSchema;
    public static function nullable(Schema $schema): NullableSchema;
    public static function union(array $schemas): UnionSchema;

    /**
     * Create schema from a PHP class using reflection.
     */
    public static function fromClass(string $className): ObjectSchema;

    /**
     * Convert schema to JSON Schema format.
     */
    abstract public function toJsonSchema(): array;

    /**
     * Validate a value against this schema.
     */
    abstract public function validate(mixed $value): ValidationResult;
}
```

### 6.2 Schema with Constraints

```php
$schema = Schema::object([
    'email' => Schema::string()
        ->format('email')
        ->description('User email address'),

    'age' => Schema::integer()
        ->minimum(0)
        ->maximum(150)
        ->description('User age in years'),

    'role' => Schema::enum(['admin', 'user', 'guest'])
        ->default('user'),

    'tags' => Schema::array(Schema::string())
        ->minItems(1)
        ->maxItems(10),

    'metadata' => Schema::object([
        'createdAt' => Schema::string()->format('date-time'),
    ])->optional(),
]);
```

### 6.3 Class-Based Schemas with Attributes

```php
use SageGrids\PhpAiSdk\Core\Schema\Attributes\{
    Description,
    Minimum,
    Maximum,
    Format,
    Optional,
    ArrayItems,
};

readonly class UserProfile
{
    public function __construct(
        #[Description('User email address')]
        #[Format('email')]
        public string $email,

        #[Description('User age in years')]
        #[Minimum(0)]
        #[Maximum(150)]
        public int $age,

        #[Description('Account role')]
        public UserRole $role,

        /** @var string[] */
        #[ArrayItems(minItems: 1, maxItems: 10)]
        public array $tags,

        #[Optional]
        public ?UserMetadata $metadata = null,
    ) {}
}

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Guest = 'guest';
}
```

---

## 7. Tool System

### 7.1 Tool Definition

```php
<?php

namespace SageGrids\PhpAiSdk\Core\Tool;

final class Tool
{
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly Schema $parameters,
        private readonly ?Closure $execute = null,
    ) {}

    public static function create(
        string $name,
        string $description,
        Schema $parameters,
        ?callable $execute = null,
    ): self;

    /**
     * Create tool from a class method using reflection.
     */
    public static function fromMethod(
        object $instance,
        string $method,
    ): self;

    /**
     * Execute the tool with given arguments.
     */
    public function execute(array $arguments): mixed;

    /**
     * Check if tool has an execute handler.
     */
    public function isExecutable(): bool;

    /**
     * Convert to provider-specific format.
     */
    public function toProviderFormat(string $provider): array;
}
```

### 7.2 Tool Execution Modes

```php
// Auto-execute tools (default)
$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the weather?',
    'tools' => [$weatherTool],
    'toolChoice' => 'auto',
]);

// Manual tool execution
$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What is the weather?',
    'tools' => [$weatherTool],
    'maxToolRoundtrips' => 0, // Don't auto-execute
]);

// Handle tool calls manually
foreach ($result->toolCalls as $call) {
    if ($call->name === 'getWeather') {
        // Execute with approval/modification
        $toolResult = $weatherTool->execute($call->arguments);
        // Continue conversation with result...
    }
}
```

### 7.3 Tool from Class Method

```php
use SageGrids\PhpAiSdk\Core\Tool\Attributes\{Tool as ToolAttribute, Parameter};

class WeatherService
{
    #[ToolAttribute(
        name: 'getWeather',
        description: 'Get current weather for a location'
    )]
    public function getWeather(
        #[Parameter(description: 'City name')]
        string $location,

        #[Parameter(description: 'Temperature unit', enum: ['celsius', 'fahrenheit'])]
        string $unit = 'celsius',
    ): array {
        // Implementation
        return ['temperature' => 22, 'unit' => $unit];
    }
}

$service = new WeatherService();
$tool = Tool::fromMethod($service, 'getWeather');
```

---

## 8. Streaming Architecture

### 8.1 Generator-Based Streaming

PHP generators provide memory-efficient streaming:

```php
/**
 * @return \Generator<int, TextChunk, mixed, TextResult>
 */
public function streamText(array $options): \Generator
{
    // Generator yields chunks during streaming
    // Returns final TextResult when complete
}
```

### 8.2 SSE Stream Handling

```php
<?php

namespace SageGrids\PhpAiSdk\Http;

final class StreamingResponse
{
    /**
     * Parse SSE stream and yield events.
     *
     * @return \Generator<SSEEvent>
     */
    public function events(): \Generator
    {
        $buffer = '';

        while (!$this->stream->eof()) {
            $buffer .= $this->stream->read(8192);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $eventData = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                yield $this->parseEvent($eventData);
            }
        }
    }

    private function parseEvent(string $data): SSEEvent
    {
        // Parse SSE format: data: {...}
    }
}
```

### 8.3 Stream Consumer Patterns

```php
// Pattern 1: Iterate chunks
$stream = AI::streamText(['model' => 'openai/gpt-4o', 'prompt' => '...']);
foreach ($stream as $chunk) {
    echo $chunk->text;
}
$finalResult = $stream->getReturn();

// Pattern 2: Collect to string
$stream = AI::streamText(['model' => 'openai/gpt-4o', 'prompt' => '...']);
$fullText = '';
foreach ($stream as $chunk) {
    $fullText .= $chunk->text;
}

// Pattern 3: With callbacks
$stream = AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => '...',
    'onChunk' => function (TextChunk $chunk) {
        echo $chunk->text;
    },
    'onFinish' => function (TextResult $result) {
        echo "\n\nTokens: {$result->usage->totalTokens}";
    },
]);
iterator_to_array($stream); // Consume stream
```

---

## 9. Error Handling

### 9.1 Exception Hierarchy

```
AIException (base)
├── ProviderException
│   ├── AuthenticationException    # Invalid API key
│   ├── RateLimitException         # Rate limit exceeded
│   ├── QuotaExceededException     # Usage quota exceeded
│   ├── ModelNotFoundException     # Invalid model specified
│   └── ProviderUnavailableException # Provider API down
├── ValidationException
│   ├── SchemaValidationException  # Output doesn't match schema
│   └── InputValidationException   # Invalid input parameters
├── ToolExecutionException         # Tool execution failed
├── TimeoutException               # Request timed out
└── StreamingException             # Error during streaming
```

### 9.2 Exception Details

```php
<?php

namespace SageGrids\PhpAiSdk\Exception;

class ProviderException extends AIException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly ?int $statusCode = null,
        public readonly ?array $errorDetails = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

class RateLimitException extends ProviderException
{
    public function __construct(
        string $message,
        string $provider,
        public readonly ?int $retryAfterSeconds = null,
        ?string $model = null,
        ?int $statusCode = null,
        ?array $errorDetails = null,
        ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $provider,
            $model,
            $statusCode,
            $errorDetails,
            $requestId,
            $previous
        );
    }
}
```

### 9.3 Error Handling Example

```php
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Exception\{
    RateLimitException,
    AuthenticationException,
    ValidationException,
};

try {
    $result = AI::generateText([
        'model' => 'openai/gpt-4o',
        'prompt' => 'Hello',
    ]);
} catch (RateLimitException $e) {
    // Retry after delay
    if ($e->retryAfterSeconds) {
        sleep($e->retryAfterSeconds);
        // Retry...
    }
} catch (AuthenticationException $e) {
    // Log and alert about invalid API key
    error_log("Invalid API key for {$e->provider}");
} catch (ValidationException $e) {
    // Handle schema validation failure
    foreach ($e->errors as $error) {
        echo "Validation error at {$error->path}: {$error->message}\n";
    }
}
```

---

## 10. Event System

### 10.1 PSR-14 Compatible Events

```php
<?php

namespace SageGrids\PhpAiSdk\Event;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
}

// Events
namespace SageGrids\PhpAiSdk\Event\Events;

final readonly class RequestStarted
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $operation,
        public array $parameters,
        public float $timestamp,
    ) {}
}

final readonly class RequestCompleted
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $operation,
        public mixed $result,
        public float $duration,
        public ?Usage $usage,
    ) {}
}

final readonly class StreamChunkReceived
{
    public function __construct(
        public string $provider,
        public string $model,
        public mixed $chunk,
        public int $chunkIndex,
    ) {}
}

final readonly class ToolCallStarted
{
    public function __construct(
        public string $toolName,
        public array $arguments,
    ) {}
}

final readonly class ToolCallCompleted
{
    public function __construct(
        public string $toolName,
        public array $arguments,
        public mixed $result,
        public float $duration,
    ) {}
}

final readonly class ErrorOccurred
{
    public function __construct(
        public \Throwable $exception,
        public string $provider,
        public ?string $model,
        public string $operation,
    ) {}
}
```

### 10.2 Event Dispatcher Configuration

```php
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Event\PSR14EventDispatcher;

// With PSR-14 dispatcher (e.g., Symfony EventDispatcher)
$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
AIConfig::setEventDispatcher(new PSR14EventDispatcher($dispatcher));

// Or with custom handler
AIConfig::setEventDispatcher(new class implements EventDispatcherInterface {
    public function dispatch(object $event): object
    {
        if ($event instanceof RequestCompleted) {
            Log::info('AI request completed', [
                'provider' => $event->provider,
                'duration' => $event->duration,
                'tokens' => $event->usage?->totalTokens,
            ]);
        }
        return $event;
    }
});
```

---

## 11. Testing Support

### 11.1 Fake Provider

```php
<?php

namespace SageGrids\PhpAiSdk\Testing;

final class FakeProvider implements TextProviderInterface
{
    private array $responses = [];
    private array $recordedRequests = [];

    public function addResponse(string $operation, mixed $response): self
    {
        $this->responses[$operation][] = $response;
        return $this;
    }

    public function assertRequestMade(string $operation, ?callable $assertion = null): void
    {
        // Assert that a request was made
    }

    public function getRecordedRequests(): array
    {
        return $this->recordedRequests;
    }
}
```

### 11.2 Testing Utilities

```php
use SageGrids\PhpAiSdk\Testing\FakeProvider;
use SageGrids\PhpAiSdk\Testing\FakeResponse;
use SageGrids\PhpAiSdk\AIConfig;

// In test setup
$fake = new FakeProvider();
$fake->addResponse('generateText', FakeResponse::text('Hello, world!'));
$fake->addResponse('generateText', FakeResponse::text('Second response'));

AIConfig::setProvider('openai', $fake);

// In test
$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Say hello',
]);

$this->assertEquals('Hello, world!', $result->text);

$fake->assertRequestMade('generateText', function ($request) {
    return str_contains($request['prompt'], 'hello');
});
```

### 11.3 Streaming Test Support

```php
$fake = new FakeProvider();
$fake->addStreamResponse('streamText', FakeResponse::streamedText([
    'Hello',
    ', ',
    'world',
    '!',
]));

$stream = AI::streamText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Say hello',
]);

$chunks = [];
foreach ($stream as $chunk) {
    $chunks[] = $chunk->text;
}

$this->assertEquals(['Hello', ', ', 'world', '!'], $chunks);
```

---

## 12. Configuration

### 12.1 Global Configuration

```php
<?php

namespace SageGrids\PhpAiSdk;

final class AIConfig
{
    /**
     * Set a provider instance.
     */
    public static function setProvider(string $name, ProviderInterface $provider): void;

    /**
     * Get a provider by name.
     */
    public static function getProvider(string $name): ProviderInterface;

    /**
     * Auto-configure providers from environment variables.
     *
     * Looks for: OPENAI_API_KEY, GEMINI_API_KEY, OPENROUTER_API_KEY, etc.
     */
    public static function autoConfigureFromEnv(): void;

    /**
     * Set the HTTP client to use.
     */
    public static function setHttpClient(HttpClientInterface $client): void;

    /**
     * Set the event dispatcher.
     */
    public static function setEventDispatcher(EventDispatcherInterface $dispatcher): void;

    /**
     * Set default options for all requests.
     */
    public static function setDefaults(array $defaults): void;

    /**
     * Set request timeout in seconds.
     */
    public static function setTimeout(int $seconds): void;

    /**
     * Enable/disable retry on transient errors.
     */
    public static function setRetryEnabled(bool $enabled): void;

    /**
     * Set maximum retry attempts.
     */
    public static function setMaxRetries(int $maxRetries): void;
}
```

### 12.2 Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `OPENAI_API_KEY` | OpenAI API key | For OpenAI provider |
| `OPENAI_BASE_URL` | Custom OpenAI-compatible endpoint | No |
| `GEMINI_API_KEY` | Google Gemini API key | For Gemini provider |
| `OPENROUTER_API_KEY` | OpenRouter API key | For OpenRouter provider |
| `ELEVENLABS_API_KEY` | ElevenLabs API key | For ElevenLabs provider |
| `TAVILY_API_KEY` | Tavily API key | For Tavily provider |

---

## 13. Provider-Specific Features

### 13.1 OpenAI Provider

```php
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;

$provider = new OpenAIProvider(
    apiKey: getenv('OPENAI_API_KEY'),
    config: new OpenAIConfig(
        baseUrl: 'https://api.openai.com/v1', // Or custom endpoint
        organization: 'org-xxx',
        project: 'proj-xxx',
        timeout: 30,
    ),
);

// Supported capabilities
$provider->getCapabilities();
// TextGeneration: ✓
// Streaming: ✓
// StructuredOutput: ✓
// ToolCalling: ✓
// ImageGeneration: ✓ (DALL-E)
// SpeechGeneration: ✓ (TTS)
// Transcription: ✓ (Whisper)
// Embeddings: ✓
// Vision: ✓
```

### 13.2 Gemini Provider

```php
use SageGrids\PhpAiSdk\Provider\Gemini\GeminiProvider;

$provider = new GeminiProvider(
    apiKey: getenv('GEMINI_API_KEY'),
);

// Supported capabilities
// TextGeneration: ✓
// Streaming: ✓
// StructuredOutput: ✓
// ToolCalling: ✓
// ImageGeneration: ✓ (Imagen)
// SpeechGeneration: ✓
// Transcription: ✗
// Embeddings: ✓
// Vision: ✓
```

### 13.3 OpenRouter Provider

```php
use SageGrids\PhpAiSdk\Provider\OpenRouter\OpenRouterProvider;

$provider = new OpenRouterProvider(
    apiKey: getenv('OPENROUTER_API_KEY'),
);

// Access any model through OpenRouter
AI::generateText([
    'model' => 'openrouter/anthropic/claude-3.5-sonnet',
    'prompt' => '...',
]);

AI::generateText([
    'model' => 'openrouter/meta-llama/llama-3.1-70b-instruct',
    'prompt' => '...',
]);
```

### 13.4 ElevenLabs Provider

```php
use SageGrids\PhpAiSdk\Provider\ElevenLabs\ElevenLabsProvider;

$provider = new ElevenLabsProvider(
    apiKey: getenv('ELEVENLABS_API_KEY'),
);

// Text-to-speech
$result = AI::generateSpeech([
    'model' => 'elevenlabs/eleven_multilingual_v2',
    'text' => 'Hello world',
    'voice' => 'rachel',
    'outputFormat' => 'mp3_44100_128',
]);
```

### 13.5 Tavily Provider

```php
use SageGrids\PhpAiSdk\Provider\Tavily\TavilyProvider;

$provider = new TavilyProvider(
    apiKey: getenv('TAVILY_API_KEY'),
);

// Web search for RAG
$searchTool = $provider->createSearchTool();

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'What are the latest developments in quantum computing?',
    'tools' => [$searchTool],
]);
```

---

## 14. Dependencies

### 14.1 Required Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `php` | `^8.1` | Minimum PHP version |
| `guzzlehttp/guzzle` | `^7.0` | HTTP client |
| `psr/http-client` | `^1.0` | HTTP client interface |
| `psr/http-message` | `^1.0\|^2.0` | HTTP message interface |
| `psr/event-dispatcher` | `^1.0` | Event dispatcher interface |

### 14.2 Suggested Dependencies

| Package | Purpose |
|---------|---------|
| `symfony/event-dispatcher` | Full-featured event dispatcher |
| `monolog/monolog` | Logging |
| `phpstan/phpstan` | Static analysis |

### 14.3 Development Dependencies

| Package | Purpose |
|---------|---------|
| `phpunit/phpunit` | Testing |
| `phpstan/phpstan` | Static analysis |
| `friendsofphp/php-cs-fixer` | Code style |
| `mockery/mockery` | Mocking |

---

## 15. Implementation Phases

### Phase 1: Core SDK (MVP)

**Goal:** Match ai-sdk.dev core text generation functions

| Component | Description | Priority |
|-----------|-------------|----------|
| `AI` facade | Main entry point | P0 |
| `generateText` | Non-streaming text generation | P0 |
| `streamText` | Streaming text generation | P0 |
| `generateObject` | Structured output generation | P0 |
| Message types | User, System, Assistant messages | P0 |
| Schema system | Object, Array, String, Number, etc. | P0 |
| Tool system | Tool definition and execution | P1 |
| OpenAI provider | Full implementation | P0 |
| Gemini provider | Full implementation | P0 |
| OpenRouter provider | Basic implementation | P1 |
| Exception handling | Full hierarchy | P0 |
| Testing utilities | FakeProvider, assertions | P1 |

**Deliverables:**
- Packagist package `sage-grids/php-ai-sdk`
- Documentation: Installation, Quick Start, API Reference
- 80%+ test coverage

### Phase 2: Extended Capabilities

**Goal:** Multi-modal support and additional providers

| Component | Description | Priority |
|-----------|-------------|----------|
| `streamObject` | Streaming structured output | P0 |
| `generateImage` | Image generation | P1 |
| `generateSpeech` | Text-to-speech | P1 |
| `transcribe` | Speech-to-text | P1 |
| `embed` | Text embeddings | P1 |
| ElevenLabs provider | Speech provider | P1 |
| Tavily provider | Search provider | P2 |
| Event system | PSR-14 events | P1 |
| Retry/resilience | Automatic retries | P2 |
| MCP support | Model Context Protocol | P2 |

### Phase 3: Ecosystem

**Goal:** Framework integrations and developer tools

| Component | Description | Priority |
|-----------|-------------|----------|
| Laravel package | Service provider, facades | P1 |
| Symfony bundle | Bundle, DI integration | P1 |
| RAG support | Vector store abstraction | P2 |
| Agent abstraction | Agent interface | P2 |
| DevTools | Request/response debugging | P3 |
| Cost tracking | Usage monitoring | P2 |

---

## 16. Performance Requirements

### 16.1 Benchmarks

| Metric | Target |
|--------|--------|
| Cold start overhead | < 10ms |
| Request preparation | < 5ms |
| Memory per request | < 10MB |
| Streaming chunk latency | < 50ms added |

### 16.2 Optimizations

- Lazy provider initialization
- Connection pooling via Guzzle
- Minimal dependencies in hot path
- Efficient JSON parsing for streams
- Generator-based streaming (no buffering)

---

## 17. Security Considerations

### 17.1 API Key Handling

- Never log API keys
- Support environment variable configuration
- Clear keys from memory when possible
- Validate key format before sending

### 17.2 Input Validation

- Sanitize user inputs in prompts (optional, configurable)
- Validate schema inputs before sending to providers
- Limit maximum token/size parameters

### 17.3 Output Handling

- Validate structured outputs against schemas
- Sanitize HTML/JS in text outputs (optional)
- Handle binary data (images, audio) safely

### 17.4 Network Security

- TLS 1.2+ required for all connections
- Certificate verification enabled by default
- Timeout enforcement

---

## 18. Documentation Requirements

### 18.1 Required Documentation

| Document | Description |
|----------|-------------|
| README.md | Quick start, installation, basic usage |
| UPGRADE.md | Version upgrade guides |
| API Reference | Full API documentation (generated) |
| Provider Guides | Per-provider setup and features |
| Examples | Copy-paste-ready code examples |
| Testing Guide | How to test AI integrations |

### 18.2 Code Examples Repository

A separate `sage-grids/php-ai-sdk-examples` repository with:
- Basic usage examples
- Framework integration examples (Laravel, Symfony)
- Tool/function calling examples
- Streaming examples
- Testing examples

---

## 19. Success Metrics

### 19.1 Adoption Metrics

| Metric | 6-Month Target |
|--------|----------------|
| Packagist downloads | 100,000 |
| GitHub stars | 500 |
| Active issues resolved | 90% within 48h |
| Community contributors | 10+ |

### 19.2 Quality Metrics

| Metric | Target |
|--------|--------|
| PHPStan level | 8 (max) |
| Test coverage | 80%+ |
| Documentation coverage | 100% public API |
| Type coverage | 100% |

---

## 20. Appendix

### A. Comparison with ai-sdk.dev Functions

| ai-sdk.dev | sage-grids/php-ai-sdk | Status |
|------------|----------------------|--------|
| `generateText` | `AI::generateText()` | Phase 1 |
| `streamText` | `AI::streamText()` | Phase 1 |
| `generateObject` | `AI::generateObject()` | Phase 1 |
| `streamObject` | `AI::streamObject()` | Phase 2 |
| `generateImage` | `AI::generateImage()` | Phase 2 |
| `editImage` | Not planned | - |
| `embed` | `AI::embed()` | Phase 2 |
| Tool System | `Tool::create()` | Phase 1 |

### B. Schema Type Mapping

| JSON Schema | PHP Schema API | PHP Type |
|-------------|---------------|----------|
| `string` | `Schema::string()` | `string` |
| `number` | `Schema::number()` | `float` |
| `integer` | `Schema::integer()` | `int` |
| `boolean` | `Schema::boolean()` | `bool` |
| `array` | `Schema::array()` | `array` |
| `object` | `Schema::object()` | `object\|array` |
| `enum` | `Schema::enum()` | `string\|BackedEnum` |
| `null` | `Schema::nullable()` | `null` |

### C. Provider API Compatibility Matrix

| Feature | OpenAI | Gemini | OpenRouter |
|---------|--------|--------|------------|
| Text generation | ✓ | ✓ | ✓ |
| Streaming | ✓ | ✓ | ✓ |
| Structured output | ✓ (JSON mode) | ✓ | ✓ |
| Tool calling | ✓ | ✓ | ✓ |
| Vision | ✓ | ✓ | Depends |
| Image gen | ✓ (DALL-E) | ✓ (Imagen) | ✗ |
| TTS | ✓ | ✓ | ✗ |
| STT | ✓ (Whisper) | ✗ | ✗ |
| Embeddings | ✓ | ✓ | ✓ |

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-24 | Initial draft |
