# Architecture & Task Review

## Architecture Analysis
The proposed architecture closely mirrors the Vercel AI SDK (JavaScript), which is a strong choice for developer experience. The use of a facade (`AI` class) delegating to provider-agnostic implementations allows for a clean, unified API.

### Strengths
1.  **Provider Abstraction:** The `ProviderInterface` is well-designed to decouple application logic from specific AI vendors.
2.  **Streaming Approach:** Utilizing PHP Generators (`yield`) is the idiomatic and memory-efficient way to handle streaming in PHP.
3.  **Schema & Typing:** The Fluent API for schemas and the attribute-based `Tool` definition are excellent for modern PHP (8.1+) development.
4.  **Phasing:** The phased approach (Text -> Multimodal -> Frameworks) is logical and risk-mitigating.

## Gaps & Risks

### 1. HTTP Client & Multipart Support
*   **Issue:** The `HttpClientInterface` and `Request` class definitions in **Task 4** mention `body (string|resource)`.
*   **Gap:** File uploads (required for `transcribe` / Speech-to-Text and some Vision tasks) typically require `multipart/form-data`. A raw string body is insufficient. The HTTP abstraction must explicitly support multipart payloads to be future-proof for Phase 2.

### 2. Reflection & Complex Types
*   **Issue:** **Task 3** (`Schema::fromClass`) relies on `ReflectionClass`.
*   **Gap:** Native PHP reflection does not fully support complex array shapes (e.g., `array<string, int>`) defined in PHPDoc. It only sees `array`.
*   **Recommendation:** To achieve the goal of "100% PHPStan level 8 compatibility" and robust schema generation, the implementation may need a docblock parser (like `phpdocumentor/type-resolver`) or strictly rely on PHP 8.1+ attributes to define array item types. The task details should clarify the strategy for typed arrays.

### 3. Streaming Reliability & Retries
*   **Issue:** **Task 4** specifies "retry logic... exponential backoff".
*   **Risk:** Automatic retries for **streaming** requests are dangerous. If a stream fails after yielding partial data, retrying silently will duplicate content or corrupt the stream state.
*   **Recommendation:** Explicitly disable auto-retries for streaming requests once the first byte/event has been received, or implement a complex state recovery mechanism (unlikely for v1).

### 4. Static Configuration vs. Dependency Injection
*   **Observation:** The SDK relies heavily on static state (`AIConfig`, `AI` facade).
*   **Risk:** While this matches the JS SDK style, it can be problematic in long-running PHP environments (Swoole, RoadRunner) or rigorous DI setups.
*   **Mitigation:** Ensure that all `AI::*` methods accept a fully configured `ProviderInterface` instance in the `model` option (as hinted in the PRD) to allow completely bypassing the global static `AIConfig`.

### 5. Cost Estimation
*   **Issue:** **Task 6** mentions calculating `estimatedCost`.
*   **Risk:** Maintaining up-to-date pricing for all models in all providers is a high-maintenance burden and prone to becoming outdated.
*   **Recommendation:** Consider making it a user-injectable calculator.

## Task Specific Adjustments

*   **Task 4 (HTTP):** Update to include `MultipartRequest` or `withMultipart` support in the interface.
*   **Task 3 (Schema):** Add detail about handling array types in `fromClass` (e.g., "Support `#[ArrayItems]` attribute for typed arrays").

## Conclusion
The plan is technically sound. Addressing the Multipart support early (Task 4) is critical to avoid breaking changes when implementing Transcription later. The reflection strategy for Arrays needs to be concrete to avoid development stalls.
