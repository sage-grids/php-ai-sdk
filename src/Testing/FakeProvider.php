<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Testing;

use Generator;
use InvalidArgumentException;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Provider\EmbeddingProviderInterface;
use SageGrids\PhpAiSdk\Provider\ImageProviderInterface;
use SageGrids\PhpAiSdk\Provider\ProviderCapabilities;
use SageGrids\PhpAiSdk\Provider\SpeechProviderInterface;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\EmbeddingResult;
use SageGrids\PhpAiSdk\Result\ImageResult;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\SpeechResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\TranscriptionResult;

/**
 * A fake provider for testing AI SDK functionality without making real API calls.
 *
 * FakeProvider implements all provider interfaces and allows you to queue
 * predetermined responses for testing. It also records all requests made,
 * enabling assertions about how your code interacts with the AI.
 *
 * @example
 * ```php
 * use SageGrids\PhpAiSdk\Testing\FakeProvider;
 * use SageGrids\PhpAiSdk\Testing\FakeResponse;
 * use SageGrids\PhpAiSdk\AI;
 *
 * // Create fake provider and queue responses
 * $fake = new FakeProvider();
 * $fake->addTextResponse('Hello, world!');
 *
 * // Use with the AI facade
 * $result = AI::generateText([
 *     'model' => $fake,
 *     'prompt' => 'Say hello',
 * ]);
 *
 * // Assert the response
 * $this->assertEquals('Hello, world!', $result->text);
 *
 * // Assert the request was made correctly
 * $this->assertCount(1, $fake->getRequests());
 * $fake->assertRequestMade('generateText', function($request) {
 *     return str_contains($request->getFirstMessageContent(), 'hello');
 * });
 * ```
 */
final class FakeProvider implements
    TextProviderInterface,
    EmbeddingProviderInterface,
    ImageProviderInterface,
    SpeechProviderInterface
{
    /**
     * @var array<string, array<mixed>> Queued responses by operation.
     */
    private array $responses = [];

    /**
     * @var array<string, array<array<mixed>>> Queued streaming responses by operation.
     */
    private array $streamResponses = [];

    /**
     * @var RecordedRequest[] All recorded requests.
     */
    private array $requests = [];

    /**
     * @var ProviderCapabilities The capabilities to report.
     */
    private ProviderCapabilities $capabilities;

    /**
     * @var string[] Available model names.
     */
    private array $models;

    /**
     * @var string The provider name.
     */
    private string $name;

    /**
     * Create a new FakeProvider instance.
     *
     * @param string $name The provider name (default: 'fake').
     * @param string[] $models Available model names.
     * @param ProviderCapabilities|null $capabilities Custom capabilities (all enabled by default).
     */
    public function __construct(
        string $name = 'fake',
        array $models = ['fake-model'],
        ?ProviderCapabilities $capabilities = null,
    ) {
        $this->name = $name;
        $this->models = $models;
        $this->capabilities = $capabilities ?? new ProviderCapabilities(
            supportsTextGeneration: true,
            supportsStreaming: true,
            supportsStructuredOutput: true,
            supportsToolCalling: true,
            supportsImageGeneration: true,
            supportsSpeechGeneration: true,
            supportsTranscription: true,
            supportsEmbeddings: true,
            supportsVision: true,
        );
    }

    /**
     * Get the provider name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the provider capabilities.
     */
    public function getCapabilities(): ProviderCapabilities
    {
        return $this->capabilities;
    }

    /**
     * Get available models.
     *
     * @return string[]
     */
    public function getAvailableModels(): array
    {
        return $this->models;
    }

    // =========================================================================
    // Response Queueing Methods
    // =========================================================================

    /**
     * Add a response to the queue for a specific operation.
     *
     * @param string $operation The operation name (e.g., 'generateText', 'embed').
     * @param mixed $response The response to return.
     * @return $this
     */
    public function addResponse(string $operation, mixed $response): self
    {
        $this->responses[$operation][] = $response;

        return $this;
    }

    /**
     * Add a text generation response to the queue.
     *
     * Convenience method for adding generateText responses.
     *
     * @param string|TextResult $response Text string or TextResult object.
     * @return $this
     */
    public function addTextResponse(string|TextResult $response): self
    {
        if (is_string($response)) {
            $response = FakeResponse::text($response);
        }

        return $this->addResponse('generateText', $response);
    }

    /**
     * Add an object generation response to the queue.
     *
     * @param mixed $object The object to return.
     * @return $this
     */
    public function addObjectResponse(mixed $object): self
    {
        $response = $object instanceof ObjectResult
            ? $object
            : FakeResponse::object($object);

        return $this->addResponse('generateObject', $response);
    }

    /**
     * Add an embedding response to the queue.
     *
     * @param float[]|float[][]|EmbeddingResult $embedding The embedding(s) to return.
     * @return $this
     */
    public function addEmbeddingResponse(array|EmbeddingResult $embedding): self
    {
        $response = $embedding instanceof EmbeddingResult
            ? $embedding
            : FakeResponse::embedding($embedding);

        return $this->addResponse('embed', $response);
    }

    /**
     * Add an image generation response to the queue.
     *
     * @param string|ImageResult $response Image URL or ImageResult object.
     * @return $this
     */
    public function addImageResponse(string|ImageResult $response): self
    {
        if (is_string($response)) {
            $response = FakeResponse::image(url: $response);
        }

        return $this->addResponse('generateImage', $response);
    }

    /**
     * Add a speech generation response to the queue.
     *
     * @param string|SpeechResult $response Audio content or SpeechResult object.
     * @return $this
     */
    public function addSpeechResponse(string|SpeechResult $response): self
    {
        if (is_string($response)) {
            $response = FakeResponse::speech($response);
        }

        return $this->addResponse('generateSpeech', $response);
    }

    /**
     * Add a transcription response to the queue.
     *
     * @param string|TranscriptionResult $response Text or TranscriptionResult object.
     * @return $this
     */
    public function addTranscriptionResponse(string|TranscriptionResult $response): self
    {
        if (is_string($response)) {
            $response = FakeResponse::transcription($response);
        }

        return $this->addResponse('transcribe', $response);
    }

    /**
     * Add streaming chunks to the queue for a specific operation.
     *
     * @param string $operation The operation name (e.g., 'streamText', 'streamObject').
     * @param array<mixed> $chunks The chunks to yield.
     * @return $this
     */
    public function addStreamResponse(string $operation, array $chunks): self
    {
        $this->streamResponses[$operation][] = $chunks;

        return $this;
    }

    /**
     * Add text streaming chunks to the queue.
     *
     * @param string[]|TextChunk[] $chunks Text parts or TextChunk objects.
     * @return $this
     */
    public function addTextStreamResponse(array $chunks): self
    {
        // Convert strings to TextChunks if needed
        if (!empty($chunks) && is_string($chunks[0])) {
            /** @var string[] $stringChunks */
            $stringChunks = $chunks;
            $chunks = FakeResponse::streamedText($stringChunks);
        }

        return $this->addStreamResponse('streamText', $chunks);
    }

    /**
     * Add object streaming chunks to the queue.
     *
     * @param mixed $finalObject The final object.
     * @param array<mixed> $partials Optional partial objects.
     * @return $this
     */
    public function addObjectStreamResponse(mixed $finalObject, array $partials = []): self
    {
        $chunks = FakeResponse::streamedObject($finalObject, $partials);

        return $this->addStreamResponse('streamObject', $chunks);
    }

    // =========================================================================
    // Request Recording and Assertions
    // =========================================================================

    /**
     * Get all recorded requests.
     *
     * @return RecordedRequest[]
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get the last recorded request.
     *
     * @return RecordedRequest|null
     */
    public function getLastRequest(): ?RecordedRequest
    {
        return $this->requests[count($this->requests) - 1] ?? null;
    }

    /**
     * Get requests for a specific operation.
     *
     * @param string $operation The operation name.
     * @return RecordedRequest[]
     */
    public function getRequestsFor(string $operation): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (RecordedRequest $r) => $r->operation === $operation,
        ));
    }

    /**
     * Get the count of requests made.
     *
     * @param string|null $operation Optional operation to filter by.
     * @return int
     */
    public function getRequestCount(?string $operation = null): int
    {
        if ($operation === null) {
            return count($this->requests);
        }

        return count($this->getRequestsFor($operation));
    }

    /**
     * Assert that a request was made with specific criteria.
     *
     * @param string $operation The expected operation.
     * @param callable|null $assertion Optional callback to validate the request.
     * @return bool True if assertion passes.
     * @throws InvalidArgumentException If no matching request found.
     */
    public function assertRequestMade(string $operation, ?callable $assertion = null): bool
    {
        $requests = $this->getRequestsFor($operation);

        if (empty($requests)) {
            throw new InvalidArgumentException(
                "No requests found for operation '{$operation}'. " .
                "Recorded operations: " . implode(', ', array_unique(array_map(
                    fn (RecordedRequest $r) => $r->operation,
                    $this->requests,
                ))),
            );
        }

        if ($assertion === null) {
            return true;
        }

        foreach ($requests as $request) {
            if ($assertion($request) === true) {
                return true;
            }
        }

        throw new InvalidArgumentException(
            "No request for '{$operation}' matched the assertion criteria.",
        );
    }

    /**
     * Assert that a specific number of requests were made.
     *
     * @param int $count Expected number of requests.
     * @param string|null $operation Optional operation to filter by.
     * @return bool True if assertion passes.
     * @throws InvalidArgumentException If count doesn't match.
     */
    public function assertRequestCount(int $count, ?string $operation = null): bool
    {
        $actual = $this->getRequestCount($operation);

        if ($actual !== $count) {
            $operationStr = $operation !== null ? " for '{$operation}'" : '';
            throw new InvalidArgumentException(
                "Expected {$count} requests{$operationStr}, but got {$actual}.",
            );
        }

        return true;
    }

    /**
     * Clear all recorded requests.
     *
     * @return $this
     */
    public function clearRequests(): self
    {
        $this->requests = [];

        return $this;
    }

    /**
     * Clear all queued responses.
     *
     * @return $this
     */
    public function clearResponses(): self
    {
        $this->responses = [];
        $this->streamResponses = [];

        return $this;
    }

    /**
     * Reset the provider to initial state.
     *
     * @return $this
     */
    public function reset(): self
    {
        return $this->clearRequests()->clearResponses();
    }

    // =========================================================================
    // TextProviderInterface Implementation
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function generateText(
        array $messages,
        ?string $model = null,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): TextResult {
        $this->recordRequest('generateText', $messages, $system, $maxTokens, $temperature, $topP, $stopSequences, $tools, $toolChoice, extraParams: ['model' => $model]);

        return $this->getNextResponse('generateText', FakeResponse::text(''));
    }

    /**
     * @inheritDoc
     */
    public function streamText(
        array $messages,
        ?string $model = null,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): Generator {
        $this->recordRequest('streamText', $messages, $system, $maxTokens, $temperature, $topP, $stopSequences, $tools, $toolChoice, extraParams: ['model' => $model]);

        $chunks = $this->getNextStreamResponse('streamText', FakeResponse::streamedText(['']));

        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    /**
     * @inheritDoc
     */
    public function generateObject(
        array $messages,
        Schema $schema,
        ?string $model = null,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
    ): ObjectResult {
        $this->recordRequest('generateObject', $messages, $system, $maxTokens, $temperature, $topP, $stopSequences, schema: $schema, extraParams: ['model' => $model]);

        return $this->getNextResponse('generateObject', FakeResponse::object([]));
    }

    /**
     * @inheritDoc
     */
    public function streamObject(
        array $messages,
        Schema $schema,
        ?string $model = null,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
    ): Generator {
        $this->recordRequest('streamObject', $messages, $system, $maxTokens, $temperature, $topP, $stopSequences, schema: $schema, extraParams: ['model' => $model]);

        $chunks = $this->getNextStreamResponse('streamObject', FakeResponse::streamedObject([]));

        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    // =========================================================================
    // EmbeddingProviderInterface Implementation
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function embed(
        string|array $input,
        ?string $model = null,
        ?int $dimensions = null,
        string $encodingFormat = 'float',
    ): EmbeddingResult {
        $this->recordRequest('embed', [], extraParams: [
            'input' => $input,
            'model' => $model,
            'dimensions' => $dimensions,
            'encodingFormat' => $encodingFormat,
        ]);

        return $this->getNextResponse('embed', FakeResponse::embedding([0.1, 0.2, 0.3]));
    }

    // =========================================================================
    // ImageProviderInterface Implementation
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function generateImage(
        string $prompt,
        ?string $model = null,
        string $size = '1024x1024',
        string $quality = 'standard',
        string $style = 'vivid',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult {
        $this->recordRequest('generateImage', [], extraParams: [
            'prompt' => $prompt,
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'style' => $style,
            'n' => $n,
            'responseFormat' => $responseFormat,
        ]);

        return $this->getNextResponse('generateImage', FakeResponse::image(count: $n));
    }

    /**
     * @inheritDoc
     */
    public function editImage(
        string $image,
        string $prompt,
        ?string $mask = null,
        ?string $model = null,
        string $size = '1024x1024',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult {
        $this->recordRequest('editImage', [], extraParams: [
            'image' => $image,
            'prompt' => $prompt,
            'mask' => $mask,
            'model' => $model,
            'size' => $size,
            'n' => $n,
            'responseFormat' => $responseFormat,
        ]);

        return $this->getNextResponse('editImage', FakeResponse::image(count: $n));
    }

    /**
     * @inheritDoc
     */
    public function createImageVariation(
        string $image,
        ?string $model = null,
        string $size = '1024x1024',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult {
        $this->recordRequest('createImageVariation', [], extraParams: [
            'image' => $image,
            'model' => $model,
            'size' => $size,
            'n' => $n,
            'responseFormat' => $responseFormat,
        ]);

        return $this->getNextResponse('createImageVariation', FakeResponse::image(count: $n));
    }

    // =========================================================================
    // SpeechProviderInterface Implementation
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function generateSpeech(
        string $text,
        string $voice,
        ?string $model = null,
        float $speed = 1.0,
        string $responseFormat = 'mp3',
    ): SpeechResult {
        $this->recordRequest('generateSpeech', [], extraParams: [
            'text' => $text,
            'voice' => $voice,
            'model' => $model,
            'speed' => $speed,
            'responseFormat' => $responseFormat,
        ]);

        return $this->getNextResponse('generateSpeech', FakeResponse::speech());
    }

    /**
     * @inheritDoc
     */
    public function transcribe(
        string $audio,
        ?string $model = null,
        ?string $language = null,
        ?string $prompt = null,
        string $responseFormat = 'json',
        ?float $temperature = null,
    ): TranscriptionResult {
        $this->recordRequest('transcribe', [], extraParams: [
            'audio' => $audio,
            'model' => $model,
            'language' => $language,
            'prompt' => $prompt,
            'responseFormat' => $responseFormat,
            'temperature' => $temperature,
        ]);

        return $this->getNextResponse('transcribe', FakeResponse::transcription(''));
    }

    /**
     * @inheritDoc
     */
    public function translateAudio(
        string $audio,
        ?string $model = null,
        ?string $prompt = null,
        string $responseFormat = 'json',
        ?float $temperature = null,
    ): TranscriptionResult {
        $this->recordRequest('translateAudio', [], extraParams: [
            'audio' => $audio,
            'model' => $model,
            'prompt' => $prompt,
            'responseFormat' => $responseFormat,
            'temperature' => $temperature,
        ]);

        return $this->getNextResponse('translateAudio', FakeResponse::transcription(''));
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Record a request.
     *
     * @param string $operation The operation name.
     * @param Message[] $messages The messages.
     * @param string|null $system System message.
     * @param int|null $maxTokens Max tokens.
     * @param float|null $temperature Temperature.
     * @param float|null $topP Top-p.
     * @param string[]|null $stopSequences Stop sequences.
     * @param Tool[]|null $tools Tools.
     * @param string|Tool|null $toolChoice Tool choice.
     * @param Schema|null $schema Schema.
     * @param array<string, mixed> $extraParams Extra parameters.
     */
    private function recordRequest(
        string $operation,
        array $messages = [],
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
        ?Schema $schema = null,
        array $extraParams = [],
    ): void {
        $this->requests[] = new RecordedRequest(
            operation: $operation,
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
            schema: $schema,
            extraParams: $extraParams,
            timestamp: microtime(true),
        );
    }

    /**
     * Get the next queued response for an operation.
     *
     * @template T
     * @param string $operation The operation name.
     * @param T $default Default response if none queued.
     * @return T
     */
    private function getNextResponse(string $operation, mixed $default): mixed
    {
        if (!isset($this->responses[$operation]) || empty($this->responses[$operation])) {
            return $default;
        }

        return array_shift($this->responses[$operation]);
    }

    /**
     * Get the next queued stream response for an operation.
     *
     * @param string $operation The operation name.
     * @param array<mixed> $default Default chunks if none queued.
     * @return array<mixed>
     */
    private function getNextStreamResponse(string $operation, array $default): array
    {
        if (!isset($this->streamResponses[$operation]) || empty($this->streamResponses[$operation])) {
            return $default;
        }

        return array_shift($this->streamResponses[$operation]);
    }
}
