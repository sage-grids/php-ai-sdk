# PHP AI SDK - Comprehensive Code Review

**Review Date:** 2026-01-30
**Version Reviewed:** Latest (commit be6b89e)
**Reviewer:** Code Quality Analysis

---

## Executive Summary

The PHP AI SDK is a well-architected library that provides a unified interface for multiple AI providers. The codebase demonstrates professional PHP development practices with strong typing, comprehensive test coverage, and clear separation of concerns. However, this review identifies several critical issues and gaps that should be addressed before production deployment.

**Overall Assessment:** The library is well-designed but has some security, thread-safety, and edge-case handling issues that need attention.

---

## Critical Issues

### 4. Missing Token Usage Accumulation ✅ RESOLVED

**Severity:** MEDIUM
**File:** `src/Core/Functions/GenerateText.php`
**Status:** Fixed in current version

~~Token usage is only captured from the final API call, not accumulated across tool roundtrips.~~

**Resolution:**
- `TextResult` now includes `roundtripUsage` array containing per-call `Usage` objects
- The `usage` property now contains accumulated totals across all tool roundtrips
- Added `withAccumulatedUsage()` method to `TextResult` for creating accumulated results
- `GenerateText::execute()` now tracks and accumulates usage across all API calls

```php
// Example: After multiple tool roundtrips
$result = generateText([...]);
$result->usage;           // Total accumulated usage across all roundtrips
$result->roundtripUsage;  // Array of Usage objects, one per API call
```

---

### 5. Inconsistent Tool Call Handling in Streaming

**Severity:** MEDIUM
**File:** `src/Core/Functions/StreamText.php`

`StreamText` does not handle tool calls at all, unlike `GenerateText` which has automatic tool execution.

```php
// StreamText::execute() - No tool handling
foreach ($generator as $chunk) {
    // Just yields chunks, ignores any tool calls
    yield $chunk;
}
```

**Impact:**
- Users expecting tool auto-execution will get unexpected behavior
- Inconsistent API surface between `generateText` and `streamText`

**Recommendation:**
- Either implement tool handling in streaming, or
- Explicitly document this limitation
- Consider a separate `streamTextWithTools` method

---

## Security Issues

### 6. API Key Exposure Risk

**Severity:** MEDIUM
**Files:** `src/Provider/OpenAI/OpenAIProvider.php`, `src/AIConfig.php`

API keys are stored in memory and could be exposed through:
- Exception traces containing object state
- Debug output or var_dump
- Memory inspection

```php
public function __construct(
    private readonly string $apiKey,  // Stored in object
```

**Recommendation:**
- Implement `__debugInfo()` to hide sensitive properties
- Consider using secure string handling or environment-only access
- Add documentation warning about exception logging

---

### 7. Cache Key May Leak Sensitive Data

**Severity:** LOW-MEDIUM
**File:** `src/Http/Middleware/CachingMiddleware.php`

The cache key includes the full request body which may contain sensitive prompts.

```php
$data = [
    'method' => $request->method,
    'uri' => $request->uri,
    'body' => $request->body,  // May contain sensitive data
];
return $this->prefix . hash('sha256', json_encode($data) ?: '');
```

**Impact:**
- Request content becomes part of cache keys
- Could leak through cache debugging or key enumeration

**Recommendation:**
- Document this behavior clearly
- Consider adding options to exclude certain fields from cache key
- Add warning about caching AI requests (non-deterministic)

---

## Input Validation Gaps

### 8. No Parameter Range Validation

**Severity:** MEDIUM
**File:** `src/Core/Functions/AbstractGenerationFunction.php`

Parameters like `temperature` and `topP` are passed through without validation.

```php
$this->temperature = isset($this->options['temperature']) ? (float) $this->options['temperature'] : null;
$this->topP = isset($this->options['topP']) ? (float) $this->options['topP'] : null;
```

**OpenAI Valid Ranges:**
- `temperature`: 0-2
- `topP`: 0-1
- `maxTokens`: 1 - model_limit

**Impact:**
- Invalid values passed to API cause unclear errors
- Provider-specific limits not enforced

**Recommendation:**
- Add validation in `AbstractGenerationFunction::parseOptions()`
- Consider provider-specific validation
- Provide clear error messages with valid ranges

---

### 9. JSON Encoding Failures Not Handled

**Severity:** LOW-MEDIUM
**Files:** Multiple provider files

`json_encode()` calls don't check for failures:

```php
// OpenAIProvider.php:615
body: json_encode($body),  // Could return false on encoding failure
```

**Impact:**
- Circular references or invalid UTF-8 could cause silent failures
- `false` would be sent as request body

**Recommendation:**
- Add `JSON_THROW_ON_ERROR` flag or explicit error checking
- Wrap in try-catch with meaningful error message

---

## Type Safety Issues

### 10. Tool Call ID Generation is Not Unique Enough

**Severity:** LOW-MEDIUM
**File:** `src/Provider/Google/GoogleProvider.php:561`

Google provider uses `uniqid()` for tool call IDs:

```php
$toolCalls[] = new ToolCall(
    id: uniqid('call_'),  // Not guaranteed unique
```

**Impact:**
- `uniqid()` is based on microsecond timestamp, not cryptographically unique
- Collision possible in high-throughput scenarios

**Recommendation:**
- Use `bin2hex(random_bytes(16))` or similar
- Consider using UUIDs

---

### 11. Schema Validation After Execution

**Severity:** LOW
**File:** `src/Core/Tool/Tool.php`

Return value validation happens after tool execution, not before potential side effects:

```php
$result = $handler($arguments);  // Executed first

// Validation happens after
if ($this->returnSchema !== null) {
    $returnValidation = $this->returnSchema->validate($result);
```

**Impact:**
- Tools with side effects might have already modified state before validation fails

**Recommendation:**
- This is expected behavior for return validation
- Consider adding pre-execution hooks for side-effect prevention

---

## Error Handling Gaps

### 12. Streaming Error State Not Cleaned Up

**Severity:** LOW-MEDIUM
**File:** `src/Provider/OpenAI/OpenAIProvider.php`

When streaming fails mid-stream, no cleanup occurs:

```php
foreach ($streamingResponse->events() as $event) {
    // If exception thrown here, accumulated state is lost
```

**Impact:**
- Partial responses may be discarded without notification
- No way to resume or retry partial streams

**Recommendation:**
- Implement partial result recovery
- Consider buffering last successful chunk for error context

---

### 13. Provider Registry Throws on Unknown Provider

**Severity:** LOW
**File:** `src/Provider/ProviderRegistry.php`

The registry throws immediately when a provider is not found:

```php
public function get(string $name): ProviderInterface
{
    if (!$this->has($name)) {
        throw new ProviderNotFoundException($name);
    }
```

**Impact:**
- No way to check existence before getting without try-catch

**Recommendation:**
- The `has()` method exists but should be documented more prominently
- Consider adding `getOrNull()` method

---

## Code Quality Observations

### 14. Large Class Responsibility

**Severity:** LOW (Suggestion)
**File:** `src/Provider/OpenAI/OpenAIProvider.php` (618 lines)

The provider class handles:
- Text generation
- Streaming
- Object generation
- Embeddings
- Request building
- Response parsing

**Recommendation:**
- Consider extracting response parsers to separate classes
- Could use composition for different capabilities

---

### 15. Unused Default Embedding Response

**Severity:** LOW
**File:** `src/Testing/FakeProvider.php`

The fake provider uses hardcoded default embedding:

```php
return $this->getNextResponse('embed', FakeResponse::embedding([0.1, 0.2, 0.3]));
```

**Impact:**
- Inconsistent dimensions with real embeddings (typically 384-3072 dimensions)

**Recommendation:**
- Use realistic default dimensions or make configurable

---

## Test Coverage Assessment

The test suite is comprehensive with 46 test files covering:
- Core functionality (Schema, Message, Tool)
- All provider implementations
- Exception handling
- HTTP middleware
- Testing utilities

**Gaps Identified:**
- Limited integration tests for full request/response cycles
- No tests for concurrent access scenarios
- Missing edge case tests for malformed API responses

---

## Recommendations Summary

### Immediate Actions (Before Production)

1. **Address thread-safety** by documenting static configuration limitations
2. **Add tool execution safeguards** with explicit whitelisting
3. **Implement memory limits** for tool roundtrips
4. **Add parameter validation** for temperature, topP, etc.
5. **Handle JSON encoding failures** with proper error messages

### Short-Term Improvements

6. **Accumulate token usage** across tool roundtrips
7. **Document streaming tool limitations** clearly
8. **Implement `__debugInfo()`** to hide API keys in debug output
9. **Use cryptographically secure IDs** for tool calls in Google provider
10. **Add request timeout** per tool execution

### Long-Term Enhancements

11. **Consider DI-based configuration** alongside static API
12. **Extract response parsers** from provider classes
13. **Add integration test suite** with mocked HTTP
14. **Implement partial stream recovery**
15. **Add conversation memory management** utilities

---

## Positive Highlights

The codebase demonstrates several excellent practices:

- **Strict typing** with `declare(strict_types=1)` everywhere
- **Immutable value objects** using `readonly` properties
- **Comprehensive exception hierarchy** with contextual information
- **PSR compliance** (PSR-4, PSR-14 compatible)
- **Testing utilities** (FakeProvider, FakeResponse) for consumer testing
- **Event-driven architecture** for extensibility
- **Middleware support** for cross-cutting concerns
- **Clear API design** inspired by Vercel AI SDK

---

## Conclusion

The PHP AI SDK is a professionally-developed library with a solid foundation. The issues identified are addressable and don't represent fundamental design flaws. Addressing the critical and security issues before production use will result in a robust, reliable SDK for AI integration in PHP applications.
