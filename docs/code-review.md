# PHP AI SDK - Comprehensive Code Review

**Review Date**: January 2026
**Codebase Version**: As of commit 320a953
**Reviewer**: Claude Opus 4.5

---

## Executive Summary

This PHP AI SDK is a well-architected library inspired by Vercel's AI SDK. The codebase demonstrates solid engineering fundamentals with modern PHP 8.1+ features. However, there are several architectural improvements and bug fixes that would elevate this library from good to production-ready.

**Overall Assessment**: 7.5/10
**Recommended for Production**: After addressing Critical and High priority items

---

## Table of Contents

1. [Critical Issues](#1-critical-issues)
2. [High Priority Improvements](#2-high-priority-improvements)
3. [Architecture Recommendations](#3-architecture-recommendations)
4. [Code Quality Issues](#4-code-quality-issues)
5. [API Design Feedback](#5-api-design-feedback)
6. [Security Considerations](#6-security-considerations)
7. [Testing Gaps](#7-testing-gaps)
8. [Documentation Improvements](#8-documentation-improvements)
9. [Performance Considerations](#9-performance-considerations)
10. [Positive Highlights](#10-positive-highlights)

---

## 1. Critical Issues

### 1.1 API Key Exposure in URL (Google Provider)

**File**: `src/Provider/Google/GoogleProvider.php:729`

```php
private function buildEndpoint(string $action): string
{
    $model = $this->config->defaultModel;
    return "/v1beta/models/{$model}{$action}?key={$this->apiKey}";
}
```

**Problem**: The API key is passed as a URL query parameter. This is a security risk because:
- URLs are logged in web server access logs
- URLs may be cached by proxies
- URLs appear in browser history (if used client-side)

**Recommendation**: Google's Gemini API supports the `x-goog-api-key` header. Use header-based authentication instead:

```php
private function buildRequest(string $method, string $endpoint, array $body): Request
{
    $headers = [
        'Content-Type' => 'application/json',
        'x-goog-api-key' => $this->apiKey,  // Add this
    ];
    // Remove ?key= from URL construction
}
```

### 1.2 Thread Safety Issues with Static State

**Files**: `src/AIConfig.php`, `src/Provider/ProviderRegistry.php`

Both classes use static mutable state which is problematic in:
- Long-running processes (workers, daemons)
- Concurrent request handling (async PHP, Swoole, ReactPHP)
- Testing (test isolation)

**Current Issues**:
```php
// AIConfig.php
private static ProviderInterface|string|null $provider = null;
private static array $defaults = [];

// ProviderRegistry.php - Singleton pattern
private static ?self $instance = null;
```

**Recommendation**: Introduce a `Container` or `Context` class that can be dependency-injected:

```php
final class AIContext
{
    public function __construct(
        private ?ProviderInterface $defaultProvider = null,
        private array $defaults = [],
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}
}

// Usage becomes:
$context = new AIContext(defaultProvider: $openaiProvider);
$result = $ai->generateText($options, $context);
```

### 1.3 Streaming May Yield Duplicate Final Chunks

**Files**: `src/Provider/OpenAI/OpenAIProvider.php:200-204`, `src/Provider/Google/GoogleProvider.php:199-201`

```php
// Ensure we yield a final chunk if we haven't already
if ($finishReason !== null && !$isFirst) {
    yield TextChunk::final($accumulatedText, '', $finishReason, $usage);
}
```

**Problem**: If `$finishReason !== null` is true inside the loop AND the condition above is also true, the final chunk may be yielded twice.

**Recommendation**: Track whether final chunk was yielded:

```php
$finalYielded = false;

foreach ($streamingResponse->events() as $event) {
    // ... inside the loop when yielding final:
    if ($finishReason !== null) {
        yield TextChunk::final(...);
        $finalYielded = true;
    }
}

if ($finishReason !== null && !$finalYielded) {
    yield TextChunk::final(...);
}
```

---

## 2. High Priority Improvements

### 2.1 Missing Model Override in Provider Methods

**Files**: All provider implementations

**Problem**: The `generateText()` and other methods use `$this->config->defaultModel` but don't accept a model parameter, making it impossible to use different models with the same provider instance.

**Current Code**:
```php
public function generateText(
    array $messages,
    ?string $system = null,
    // ... no $model parameter
): TextResult {
    $requestBody = $this->buildChatRequest(...);  // Uses defaultModel
}
```

**Recommendation**: Add `$model` parameter to all provider methods:

```php
public function generateText(
    array $messages,
    ?string $model = null,  // Add this
    ?string $system = null,
    // ...
): TextResult {
    $effectiveModel = $model ?? $this->config->defaultModel;
}
```

This also requires updating `TextProviderInterface` and all implementations.

### 2.2 Inconsistent Exception Hierarchy

**Problem**: There are two parallel exception hierarchies:

1. SDK-level exceptions (`src/Exception/`): `AIException` -> `ProviderException` -> specific exceptions
2. Provider-specific exceptions (`src/Provider/*/Exception/`): `OpenAIException`, `GoogleException`, `OpenRouterException`

This creates confusion about which exceptions to catch:

```php
// Which should users catch?
try {
    AI::generateText([...]);
} catch (ProviderException $e) {           // SDK-level
} catch (OpenAIException $e) {              // Provider-specific
} catch (Google\AuthenticationException $e) {  // Provider-specific nested
}
```

**Recommendation**: Choose one approach. I recommend making provider-specific exceptions extend SDK exceptions:

```php
// Provider-specific exceptions should extend SDK exceptions
namespace SageGrids\PhpAiSdk\Provider\OpenAI\Exception;

class OpenAIException extends \SageGrids\PhpAiSdk\Exception\ProviderException
{
    public function __construct(string $message, ...) {
        parent::__construct($message, provider: 'openai', ...);
    }
}
```

### 2.3 No Retry Logic for Streaming Errors

**File**: `src/Http/GuzzleHttpClient.php:83-85`

```php
// Separate client for streaming WITHOUT retry middleware
// Retrying a stream after receiving data would cause corruption/duplication
```

**Problem**: While the comment correctly identifies why retries are disabled, streaming requests have no error recovery at all. If a connection drops mid-stream, the user loses all data.

**Recommendation**: Implement resumable streaming or at least buffer accumulated data before erroring:

```php
public function stream(Request $request): StreamingResponse
{
    try {
        $guzzleResponse = $this->streamClient->request(...);
        return new StreamingResponse($guzzleResponse->getBody());
    } catch (RequestException $e) {
        // For connection errors BEFORE data starts, retry is safe
        if ($this->isRetryableConnectionError($e)) {
            return $this->stream($request);  // Retry
        }
        throw $e;
    }
}
```

### 2.4 Tool Execution Doesn't Validate Return Types

**File**: `src/Core/Tool/Tool.php:115-133`

```php
public function execute(array $arguments): mixed
{
    // Validates input arguments
    $validation = $this->parameters->validate($arguments);
    // ...
    return $handler($arguments);  // No output validation
}
```

**Problem**: Tool return values are not validated. If a tool returns invalid data, it may cause downstream issues or confusing error messages.

**Recommendation**: Add optional return schema validation:

```php
final class Tool
{
    public function __construct(
        // ...
        private readonly ?Schema $returnSchema = null,
    ) {}

    public function execute(array $arguments): mixed
    {
        $result = $handler($arguments);

        if ($this->returnSchema !== null) {
            $validation = $this->returnSchema->validate($result);
            if (!$validation->isValid) {
                throw new ToolExecutionException(...);
            }
        }

        return $result;
    }
}
```

---

## 3. Architecture Recommendations

### 3.1 Consider Using DTOs Instead of Arrays for Options

**Current Pattern**:
```php
AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Hello',
    'temperature' => 0.7,
]);
```

**Problems**:
- No IDE autocomplete for option keys
- Typos in option names fail silently or throw runtime errors
- Type validation happens at runtime only

**Recommendation**: Create option DTOs:

```php
final readonly class TextGenerationOptions
{
    public function __construct(
        public string|ProviderInterface $model,
        public ?string $prompt = null,
        public ?array $messages = null,
        public ?string $system = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        // ...
    ) {}
}

// Usage with IDE support:
AI::generateText(new TextGenerationOptions(
    model: 'openai/gpt-4o',
    prompt: 'Hello',
    temperature: 0.7,
));

// Keep array syntax for backwards compatibility:
AI::generateText(['model' => '...']);  // Internally converted to DTO
```

### 3.2 Decouple Message Formatting from Providers

**Problem**: Each provider implements its own message formatting logic with significant code duplication.

**Files**:
- `OpenAIProvider.php:488-510` - `formatMessages()`
- `GoogleProvider.php:501-557` - `formatMessages()`
- `OpenRouterProvider.php` - Similar pattern

**Recommendation**: Extract message formatting into a strategy pattern:

```php
interface MessageFormatterInterface
{
    public function format(array $messages, ?string $system): array;
}

class OpenAIMessageFormatter implements MessageFormatterInterface { }
class GeminiMessageFormatter implements MessageFormatterInterface { }

// Providers become simpler:
final class OpenAIProvider implements TextProviderInterface
{
    public function __construct(
        private MessageFormatterInterface $formatter = new OpenAIMessageFormatter(),
    ) {}
}
```

### 3.3 Add Middleware/Pipeline Support

**Current State**: Events provide observation but no interception.

**Recommendation**: Add middleware support for request/response modification:

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}

class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $this->logger->info('Request', $request->toArray());
        $response = $next($request);
        $this->logger->info('Response', $response->toArray());
        return $response;
    }
}

// Usage:
$provider = new OpenAIProvider($apiKey);
$provider->addMiddleware(new LoggingMiddleware($logger));
$provider->addMiddleware(new CachingMiddleware($cache));
```

### 3.4 Add Proper Model Configuration

**Problem**: Model-specific settings (context window, pricing, capabilities) are hardcoded or absent.

**Recommendation**: Create a model registry with metadata:

```php
final readonly class ModelInfo
{
    public function __construct(
        public string $id,
        public string $provider,
        public int $contextWindow,
        public bool $supportsVision,
        public bool $supportsTools,
        public ?float $inputPricePerMToken = null,
        public ?float $outputPricePerMToken = null,
    ) {}
}

class ModelRegistry
{
    public function get(string $modelId): ModelInfo;
    public function supports(string $modelId, string $capability): bool;
}
```

---

## 4. Code Quality Issues

### 4.1 Missing `declare(strict_types=1)`

**Files Missing Declaration**:
- `src/Provider/ProviderInterface.php`
- `src/Provider/OpenAI/OpenAIProvider.php`
- `src/Provider/Google/GoogleProvider.php`
- `src/Core/Schema/Schema.php`
- `src/Core/Tool/Tool.php`
- `src/Http/GuzzleHttpClient.php`
- Most files in `src/`

**Recommendation**: Add `declare(strict_types=1);` to all PHP files for consistent type enforcement.

### 4.2 Inconsistent Readonly Usage

Some classes are `readonly`, others have readonly properties, others have neither:

```php
// Fully readonly (good)
final readonly class TextResult { }

// Non-readonly class with readonly properties (inconsistent)
final class OpenAIProvider {
    private readonly string $apiKey;  // Property is readonly
    private HttpClientInterface $httpClient;  // This is not
}

// Neither (should be readonly)
class ProviderCapabilities {
    public function __construct(
        public bool $supportsTextGeneration,  // Should be readonly
    ) {}
}
```

**Recommendation**: Use `final readonly class` consistently for value objects and configuration.

### 4.3 PHPDoc Inconsistencies

**File**: `src/Provider/TextProviderInterface.php`

```php
/**
 * @param Message[] $messages The conversation messages.
 */
public function generateText(
    array $messages,  // No type hint enforcement at runtime
```

**Problem**: PHPDoc says `Message[]` but PHP doesn't enforce this at runtime. Invalid input could cause confusing errors deep in the code.

**Recommendation**: Add runtime validation or use PHPStan generics properly:

```php
/**
 * @param list<Message> $messages
 */
public function generateText(array $messages, ...): TextResult
{
    foreach ($messages as $i => $message) {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException(
                "messages[$i] must be instance of Message"
            );
        }
    }
}
```

### 4.4 Magic Strings Should Be Constants

**File**: `src/Provider/OpenAI/OpenAIProvider.php`

```php
private function formatToolChoice(string|Tool $toolChoice): string|array
{
    if ($toolChoice instanceof Tool) {
        return ['type' => 'function', ...];  // 'function' is magic string
    }
    return $toolChoice;  // 'auto', 'none', 'required' are magic strings
}
```

**Recommendation**: Define constants or an enum:

```php
enum ToolChoice: string
{
    case Auto = 'auto';
    case None = 'none';
    case Required = 'required';
}
```

### 4.5 Overly Permissive Error Handling

**File**: `src/Core/Functions/GenerateText.php:136-145`

```php
if ($tool === null) {
    // Tool not found, add error message
    $messages[] = new ToolMessage(
        $toolCall->id,
        "Error: Tool '{$toolCall->name}' not found"
    );
    continue;  // Silently continues
}
```

**Problem**: Tool not found is silently handled by adding an error message. This may mask configuration issues.

**Recommendation**: Make this configurable or throw by default:

```php
if ($tool === null) {
    if ($this->strictToolResolution) {
        throw new ToolNotFoundException($toolCall->name);
    }
    // Fall back to error message
}
```

---

## 5. API Design Feedback

### 5.1 Rename `generateText` to More Accurate Name

**Problem**: `generateText()` does more than generate text - it handles tool calls, multi-turn conversations, etc.

**Recommendation**: Consider names that better reflect the capability:
- `chat()` - More accurate for conversation
- `complete()` - Common industry term
- Keep `generateText()` for simple completion, add `chat()` for full features

### 5.2 Schema Factory Method Naming

**Current**:
```php
Schema::string()
Schema::integer()
Schema::array(Schema::string())
```

**Issue**: `Schema::array()` shadows PHP's `array` type hint in some contexts.

**Recommendation**: Use more explicit names:

```php
Schema::arrayOf(Schema::string())
Schema::listOf(Schema::string())
```

### 5.3 Consider Builder Pattern for Complex Options

**Current**:
```php
AI::generateText([
    'model' => 'openai/gpt-4o',
    'prompt' => 'Hello',
    'temperature' => 0.7,
    'maxTokens' => 1000,
    'tools' => [$weatherTool],
    'toolChoice' => 'auto',
    'onChunk' => fn($chunk) => echo $chunk->delta,
]);
```

**Recommendation**: Add fluent builder for complex use cases:

```php
AI::text()
    ->model('openai/gpt-4o')
    ->prompt('Hello')
    ->temperature(0.7)
    ->maxTokens(1000)
    ->tools([$weatherTool])
    ->onChunk(fn($chunk) => echo $chunk->delta)
    ->generate();
```

### 5.4 Inconsistent Method Naming

- `generateText()` vs `streamText()` - consistent
- `generateObject()` vs `streamObject()` - consistent
- `embed()` - why not `generateEmbedding()` for consistency?

### 5.5 Missing Convenience Methods

**Recommendation**: Add helper methods for common operations:

```php
// Current
$result = AI::generateText(['model' => 'openai/gpt-4o', 'prompt' => 'Hi']);
echo $result->text;

// Proposed shorthand
$text = AI::ask('openai/gpt-4o', 'Hi');
```

---

## 6. Security Considerations

### 6.1 No Input Sanitization for Tool Arguments

**File**: `src/Core/Tool/Tool.php:115-133`

Tool arguments from AI models are passed directly to user functions without sanitization beyond schema validation.

**Recommendation**: Add sanitization layer:

```php
public function execute(array $arguments): mixed
{
    $sanitized = $this->sanitizer->sanitize($arguments, $this->parameters);
    return $handler($sanitized);
}
```

### 6.2 Logging May Expose Sensitive Data

**Problem**: The event system allows logging of requests/responses which may contain:
- API keys (if improperly included)
- PII in prompts
- Sensitive business data

**Recommendation**: Add data masking utilities:

```php
class SensitiveDataMasker
{
    public function mask(array $data, array $sensitiveKeys = ['apiKey', 'password']): array;
}
```

### 6.3 No Rate Limiting on Client Side

**Problem**: The SDK has no client-side rate limiting. A bug in user code could rapidly exhaust API quotas.

**Recommendation**: Add optional rate limiting:

```php
$provider = new OpenAIProvider($apiKey, config: new OpenAIConfig(
    rateLimiter: new TokenBucketRateLimiter(
        requestsPerMinute: 60,
        tokensPerMinute: 90000,
    ),
));
```

---

## 7. Testing Gaps

### 7.1 Missing Integration Tests

**Current State**: Only unit tests exist. No integration tests against real APIs.

**Recommendation**: Add integration test suite (marked to skip in CI without API keys):

```php
/**
 * @group integration
 * @requires env OPENAI_API_KEY
 */
class OpenAIIntegrationTest extends TestCase
{
    public function testRealApiCall(): void
    {
        $provider = new OpenAIProvider(getenv('OPENAI_API_KEY'));
        $result = $provider->generateText([new UserMessage('Say hello')]);
        $this->assertNotEmpty($result->text);
    }
}
```

### 7.2 Missing Edge Case Tests

- Empty message array handling
- Very long prompts (context window limits)
- Malformed API responses
- Network timeout scenarios
- Concurrent request handling

### 7.3 No Fuzz Testing for Schema Validation

**Recommendation**: Add property-based testing for schema validation:

```php
use Eris\Generator;
use Eris\TestTrait;

class SchemaFuzzTest extends TestCase
{
    use TestTrait;

    public function testStringSchemaHandlesAnyInput(): void
    {
        $this->forAll(Generator\string())->then(function ($input) {
            $schema = Schema::string();
            // Should not throw, should return valid ValidationResult
            $result = $schema->validate($input);
            $this->assertInstanceOf(ValidationResult::class, $result);
        });
    }
}
```

### 7.4 FakeProvider Should Match Real Provider Behavior More Closely

**File**: `src/Testing/FakeProvider.php`

**Problem**: FakeProvider doesn't validate that options would be valid for real providers.

**Recommendation**: Add strict mode:

```php
$fake = new FakeProvider(strict: true);
// In strict mode, validates options match real provider expectations
```

---

## 8. Documentation Improvements

### 8.1 Missing CHANGELOG

**Recommendation**: Add `CHANGELOG.md` following Keep a Changelog format.

### 8.2 Missing UPGRADE Guide

**Recommendation**: When making breaking changes, provide `UPGRADE.md` with migration instructions.

### 8.3 README Could Use More Examples

Specific gaps:
- Multi-turn conversation example
- Error handling best practices
- Testing with FakeProvider full example
- Streaming with error handling

### 8.4 Missing PHPDoc on Many Public Methods

**Example** (`src/Core/Schema/ObjectSchema.php`):
```php
public function additionalProperties(bool $allow): static
{
    // No PHPDoc explaining what this does
}
```

### 8.5 Add Architecture Documentation

**Recommendation**: Add `docs/ARCHITECTURE.md` explaining:
- Component relationships
- Data flow diagrams
- Extension points
- Design decisions rationale

---

## 9. Performance Considerations

### 9.1 Schema Reflection Could Be Cached

**File**: `src/Core/Schema/Schema.php:108-127`

```php
public static function fromClass(string $className): ObjectSchema
{
    $ref = new ReflectionClass($className);
    // Reflection is done every time
}
```

**Recommendation**: Add schema caching:

```php
private static array $schemaCache = [];

public static function fromClass(string $className): ObjectSchema
{
    if (!isset(self::$schemaCache[$className])) {
        self::$schemaCache[$className] = self::buildSchemaFromClass($className);
    }
    return clone self::$schemaCache[$className];
}
```

### 9.2 Consider Connection Pooling

**File**: `src/Http/GuzzleHttpClient.php`

**Current**: New Guzzle client per provider instance.

**Recommendation**: For high-throughput scenarios, consider connection pooling:

```php
class ConnectionPool
{
    private static array $clients = [];

    public static function getClient(string $baseUrl): Client
    {
        // Return existing client for same host
    }
}
```

### 9.3 JSON Encoding/Decoding Could Be Optimized

Multiple places decode JSON then re-encode it. Consider using streaming JSON parsing for large responses.

---

## 10. Positive Highlights

### What's Done Well

1. **Modern PHP Features**: Excellent use of PHP 8.1+ features including:
   - Readonly classes and properties
   - Enums for type-safe constants
   - Union types
   - Named arguments
   - Constructor property promotion

2. **Clean Architecture**: Good separation of concerns:
   - Providers are self-contained
   - Core functions are well-abstracted
   - Testing utilities are comprehensive

3. **API Design**: The `AI::generateText()` facade is intuitive and matches the Vercel AI SDK mental model.

4. **Testing Infrastructure**: The `FakeProvider` and `AITestCase` utilities make testing user code much easier.

5. **Event System**: PSR-14 compliance allows clean observability without coupling.

6. **Error Handling Structure**: The exception hierarchy provides good granularity for different error types.

7. **Documentation**: The README is comprehensive with practical examples.

8. **Type Safety**: Good use of PHPDoc annotations for static analysis tools.

9. **Streaming Support**: Well-implemented streaming with generators - memory efficient.

10. **Multi-Provider Support**: Clean abstraction allows switching providers easily.

---

## Summary of Recommendations by Priority

### Critical (Fix Before Production)
- [ ] Move Google API key from URL to header
- [ ] Fix duplicate final chunk streaming bug
- [ ] Add `declare(strict_types=1)` to all files

### High Priority
- [ ] Add model parameter to provider methods
- [ ] Unify exception hierarchy
- [ ] Add connection error recovery for streaming
- [ ] Add tool return validation

### Medium Priority
- [ ] Create option DTOs
- [ ] Extract message formatters
- [ ] Add middleware support
- [ ] Create model registry
- [ ] Improve thread safety

### Low Priority (Nice to Have)
- [ ] Add fluent builder API
- [ ] Add schema caching
- [ ] Add rate limiting
- [ ] Add integration tests
- [ ] Expand documentation

---

*This review was conducted on the codebase as of commit 320a953. Recommendations are based on common PHP best practices, SOLID principles, and comparison with similar SDKs in other languages.*
