<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\Google;

use Generator;
use SageGrids\PhpAiSdk\Core\Message\Formatter\GeminiMessageFormatter;
use SageGrids\PhpAiSdk\Core\Message\Formatter\MessageFormatterInterface;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Http\GuzzleHttpClient;
use SageGrids\PhpAiSdk\Http\HttpClientInterface;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Provider\Google\Exception\GoogleException;
use SageGrids\PhpAiSdk\Provider\ProviderCapabilities;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Http\Middleware\HasMiddleware;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\Usage;

/**
 * Google Gemini provider implementation supporting text generation, streaming,
 * structured output, and tool calling.
 */
final class GoogleProvider implements TextProviderInterface
{
    use HasMiddleware;
    private HttpClientInterface $httpClient;
    private GoogleConfig $config;
    private MessageFormatterInterface $messageFormatter;

    /**
     * Available Google Gemini models.
     */
    private const AVAILABLE_MODELS = [
        'gemini-2.0-flash-exp',
        'gemini-1.5-pro',
        'gemini-1.5-flash',
        'gemini-1.0-pro',
    ];

    public function __construct(
        private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?GoogleConfig $config = null,
        ?MessageFormatterInterface $messageFormatter = null,
    ) {
        $this->config = $config ?? new GoogleConfig();
        $this->httpClient = $httpClient ?? new GuzzleHttpClient(
            timeout: $this->config->timeout,
        );
        $this->messageFormatter = $messageFormatter ?? new GeminiMessageFormatter();
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTextGeneration: true,
            supportsStreaming: true,
            supportsStructuredOutput: true,
            supportsToolCalling: true,
            supportsImageGeneration: false,
            supportsSpeechGeneration: false,
            supportsTranscription: false,
            supportsEmbeddings: false, // Gemini uses different API for embeddings
            supportsVision: true,
        );
    }

    /**
     * @return string[]
     */
    public function getAvailableModels(): array
    {
        return self::AVAILABLE_MODELS;
    }

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
        $requestBody = $this->buildGenerateRequest(
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
        );

        $endpoint = $this->buildEndpoint(':generateContent', $model);
        $response = $this->request('POST', $endpoint, $requestBody);

        return $this->parseTextResponse($response);
    }

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
        $requestBody = $this->buildGenerateRequest(
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
        );

        $endpoint = $this->buildEndpoint(':streamGenerateContent', $model);
        // Add alt=sse for Server-Sent Events format
        $endpoint .= '?alt=sse';

        $request = $this->buildRequest('POST', $endpoint, $requestBody);
        $streamingResponse = $this->httpClient->stream($request);

        $accumulatedText = '';
        $finishReason = null;
        $usage = null;
        $isFirst = true;
        $finalYielded = false;

        foreach ($streamingResponse->events() as $event) {
            $data = $event->data;

            if ($data === '[DONE]') {
                break;
            }

            if (!is_array($data)) {
                continue;
            }

            // Extract usage metadata
            if (isset($data['usageMetadata']) && is_array($data['usageMetadata'])) {
                $usage = $this->parseUsageMetadata($data['usageMetadata']);
            }

            /** @var list<array<string, mixed>> $candidates */
            $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];
            if (empty($candidates)) {
                continue;
            }

            /** @var array<string, mixed> $candidate */
            $candidate = $candidates[0];
            /** @var array<string, mixed> $content */
            $content = is_array($candidate['content'] ?? null) ? $candidate['content'] : [];
            /** @var list<array<string, mixed>> $parts */
            $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
            $delta = '';

            foreach ($parts as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $delta .= $part['text'];
                }
            }

            if (isset($candidate['finishReason']) && is_string($candidate['finishReason'])) {
                $finishReason = $this->mapFinishReason($candidate['finishReason']);
            }

            if ($delta !== '') {
                $accumulatedText .= $delta;

                if ($isFirst) {
                    yield TextChunk::first($delta);
                    $isFirst = false;
                } elseif ($finishReason !== null) {
                    yield TextChunk::final($accumulatedText, $delta, $finishReason, $usage);
                    $finalYielded = true;
                } else {
                    yield TextChunk::continue($accumulatedText, $delta);
                }
            }
        }

        // Ensure we yield a final chunk if we haven't already
        if (!$finalYielded && $finishReason !== null && !$isFirst) {
            yield TextChunk::final($accumulatedText, '', $finishReason, $usage);
        }
    }

    /**
     * @return ObjectResult<mixed>
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
        $requestBody = $this->buildGenerateRequest(
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: null,
            toolChoice: null,
        );

        // Configure response to return JSON
        /** @var array<string, mixed> $existingConfig */
        $existingConfig = is_array($requestBody['generationConfig'] ?? null) ? $requestBody['generationConfig'] : [];
        $requestBody['generationConfig'] = array_merge(
            $existingConfig,
            [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->convertToGeminiSchema($schema->toJsonSchema()),
            ]
        );

        $endpoint = $this->buildEndpoint(':generateContent', $model);
        $response = $this->request('POST', $endpoint, $requestBody);

        /** @var list<array<string, mixed>> $candidates */
        $candidates = is_array($response['candidates'] ?? null) ? $response['candidates'] : [];
        /** @var array<string, mixed> $candidate */
        $candidate = $candidates[0] ?? [];
        /** @var array<string, mixed> $content */
        $content = is_array($candidate['content'] ?? null) ? $candidate['content'] : [];
        /** @var list<array<string, mixed>> $parts */
        $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        /** @var string|null $finishReasonRaw */
        $finishReasonRaw = isset($candidate['finishReason']) && is_string($candidate['finishReason'])
            ? $candidate['finishReason']
            : null;
        $finishReason = $this->mapFinishReason($finishReasonRaw);

        $usage = isset($response['usageMetadata']) && is_array($response['usageMetadata'])
            ? $this->parseUsageMetadata($response['usageMetadata'])
            : null;

        $object = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new GoogleException(
                'Failed to parse JSON response: ' . json_last_error_msg(),
                0,
                ['raw_content' => $text],
            );
        }

        // Validate against schema
        $validation = $schema->validate($object);
        if (!$validation->isValid) {
            throw new GoogleException(
                'Response does not match schema: ' . implode(', ', $validation->errors),
                0,
                ['object' => $object, 'errors' => $validation->errors],
            );
        }

        return new ObjectResult(
            object: $object,
            text: $text,
            finishReason: $finishReason,
            usage: $usage,
            rawResponse: $response,
        );
    }

    /**
     * @return Generator<ObjectChunk<mixed>>
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
        $requestBody = $this->buildGenerateRequest(
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: null,
            toolChoice: null,
        );

        // Configure response to return JSON
        /** @var array<string, mixed> $existingConfig */
        $existingConfig = is_array($requestBody['generationConfig'] ?? null) ? $requestBody['generationConfig'] : [];
        $requestBody['generationConfig'] = array_merge(
            $existingConfig,
            [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->convertToGeminiSchema($schema->toJsonSchema()),
            ]
        );

        $endpoint = $this->buildEndpoint(':streamGenerateContent', $model);
        $endpoint .= '?alt=sse';

        $request = $this->buildRequest('POST', $endpoint, $requestBody);
        $streamingResponse = $this->httpClient->stream($request);

        $accumulatedJson = '';
        $finishReason = null;
        $usage = null;
        $finalYielded = false;

        foreach ($streamingResponse->events() as $event) {
            $data = $event->data;

            if ($data === '[DONE]') {
                break;
            }

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['usageMetadata']) && is_array($data['usageMetadata'])) {
                $usage = $this->parseUsageMetadata($data['usageMetadata']);
            }

            /** @var list<array<string, mixed>> $candidates */
            $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];
            if (empty($candidates)) {
                continue;
            }

            /** @var array<string, mixed> $candidate */
            $candidate = $candidates[0];
            /** @var array<string, mixed> $content */
            $content = is_array($candidate['content'] ?? null) ? $candidate['content'] : [];
            /** @var list<array<string, mixed>> $parts */
            $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
            $delta = '';

            foreach ($parts as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $delta .= $part['text'];
                }
            }

            if (isset($candidate['finishReason']) && is_string($candidate['finishReason'])) {
                $finishReason = $this->mapFinishReason($candidate['finishReason']);
            }

            if ($delta !== '') {
                $accumulatedJson .= $delta;

                // Try to parse partial JSON
                $partialObject = json_decode($accumulatedJson, true);

                if ($finishReason !== null) {
                    // Final chunk - validate the complete object
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $validation = $schema->validate($partialObject);
                        if (!$validation->isValid) {
                            throw new GoogleException(
                                'Response does not match schema: ' . implode(', ', $validation->errors),
                                0,
                                ['object' => $partialObject, 'errors' => $validation->errors],
                            );
                        }
                        yield ObjectChunk::final($partialObject, $accumulatedJson, $finishReason, $usage);
                        $finalYielded = true;
                    } else {
                        throw new GoogleException(
                            'Failed to parse JSON response: ' . json_last_error_msg(),
                            0,
                            ['raw_content' => $accumulatedJson],
                        );
                    }
                } else {
                    // Partial chunk - yield even if JSON is incomplete
                    yield ObjectChunk::partial($partialObject, $accumulatedJson);
                }
            }
        }

        // Ensure final chunk is yielded if not already
        if (!$finalYielded && $finishReason !== null) {
            $finalObject = json_decode($accumulatedJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                yield ObjectChunk::final($finalObject, $accumulatedJson, $finishReason, $usage);
            }
        }
    }

    /**
     * Build the request body for a generate content request.
     *
     * @param Message[] $messages
     * @param string[]|null $stopSequences
     * @param Tool[]|null $tools
     * @return array<string, mixed>
     */
    private function buildGenerateRequest(
        array $messages,
        ?string $system,
        ?int $maxTokens,
        ?float $temperature,
        ?float $topP,
        ?array $stopSequences,
        ?array $tools,
        string|Tool|null $toolChoice,
    ): array {
        $formatted = $this->messageFormatter->format($messages, $system);

        $requestBody = [
            'contents' => $formatted['contents'],
        ];

        // System instruction from formatter
        if (isset($formatted['systemInstruction'])) {
            $requestBody['systemInstruction'] = $formatted['systemInstruction'];
        }

        // Build generation config
        $generationConfig = [];

        if ($maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }

        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }

        if ($topP !== null) {
            $generationConfig['topP'] = $topP;
        }

        if ($stopSequences !== null && !empty($stopSequences)) {
            $generationConfig['stopSequences'] = $stopSequences;
        }

        if (!empty($generationConfig)) {
            $requestBody['generationConfig'] = $generationConfig;
        }

        // Add tools (function declarations)
        if ($tools !== null && !empty($tools)) {
            $requestBody['tools'] = [
                [
                    'functionDeclarations' => array_map(
                        fn (Tool $tool) => $tool->toGeminiFormat(),
                        $tools
                    ),
                ],
            ];

            if ($toolChoice !== null) {
                $requestBody['toolConfig'] = [
                    'functionCallingConfig' => $this->formatToolChoice($toolChoice),
                ];
            }
        }

        return $requestBody;
    }

    /**
     * Format the tool choice parameter for Gemini's functionCallingConfig.
     *
     * @return array<string, mixed>
     */
    private function formatToolChoice(string|Tool $toolChoice): array
    {
        if ($toolChoice instanceof Tool) {
            return [
                'mode' => 'ANY',
                'allowedFunctionNames' => [$toolChoice->name],
            ];
        }

        return match ($toolChoice) {
            'auto' => ['mode' => 'AUTO'],
            'none' => ['mode' => 'NONE'],
            'required' => ['mode' => 'ANY'],
            default => ['mode' => 'AUTO'],
        };
    }

    /**
     * Parse the response from a text generation request.
     *
     * @param array<string, mixed> $response
     */
    private function parseTextResponse(array $response): TextResult
    {
        /** @var list<array<string, mixed>> $candidates */
        $candidates = is_array($response['candidates'] ?? null) ? $response['candidates'] : [];
        /** @var array<string, mixed> $candidate */
        $candidate = $candidates[0] ?? [];
        /** @var array<string, mixed> $content */
        $content = is_array($candidate['content'] ?? null) ? $candidate['content'] : [];
        /** @var list<array<string, mixed>> $parts */
        $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
        $text = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }

            // Handle function calls
            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                /** @var array<string, mixed> $functionCall */
                $functionCall = $part['functionCall'];
                /** @var string $functionName */
                $functionName = is_string($functionCall['name'] ?? null) ? $functionCall['name'] : '';
                /** @var array<string, mixed> $functionArgs */
                $functionArgs = is_array($functionCall['args'] ?? null) ? $functionCall['args'] : [];

                $toolCalls[] = new ToolCall(
                    id: uniqid('call_'),
                    name: $functionName,
                    arguments: $functionArgs,
                );
            }
        }

        /** @var string|null $finishReasonRaw */
        $finishReasonRaw = isset($candidate['finishReason']) && is_string($candidate['finishReason'])
            ? $candidate['finishReason']
            : null;
        $finishReason = $this->mapFinishReason($finishReasonRaw);

        $usage = isset($response['usageMetadata']) && is_array($response['usageMetadata'])
            ? $this->parseUsageMetadata($response['usageMetadata'])
            : null;

        return new TextResult(
            text: $text,
            finishReason: $finishReason,
            usage: $usage,
            toolCalls: $toolCalls,
            rawResponse: $response,
        );
    }

    /**
     * Map Gemini finish reason to our standard enum.
     */
    private function mapFinishReason(?string $reason): ?FinishReason
    {
        if ($reason === null) {
            return null;
        }

        return match (strtoupper($reason)) {
            'STOP' => FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY' => FinishReason::ContentFilter,
            'RECITATION' => FinishReason::ContentFilter,
            default => null,
        };
    }

    /**
     * Parse usage metadata from Gemini response.
     *
     * @param array<string, mixed> $usageMetadata
     */
    private function parseUsageMetadata(array $usageMetadata): Usage
    {
        /** @var int|string $promptTokenCount */
        $promptTokenCount = $usageMetadata['promptTokenCount'] ?? 0;
        /** @var int|string $candidatesTokenCount */
        $candidatesTokenCount = $usageMetadata['candidatesTokenCount'] ?? 0;
        /** @var int|string $totalTokenCount */
        $totalTokenCount = $usageMetadata['totalTokenCount'] ?? 0;

        return new Usage(
            promptTokens: (int) $promptTokenCount,
            completionTokens: (int) $candidatesTokenCount,
            totalTokens: (int) $totalTokenCount,
        );
    }

    /**
     * Convert JSON Schema to Gemini's native schema format.
     *
     * @param array<string, mixed> $jsonSchema
     * @return array<string, mixed>
     */
    private function convertToGeminiSchema(array $jsonSchema): array
    {
        $geminiSchema = [];

        if (isset($jsonSchema['type']) && is_string($jsonSchema['type'])) {
            $geminiSchema['type'] = strtoupper($jsonSchema['type']);
        }

        if (isset($jsonSchema['description'])) {
            $geminiSchema['description'] = $jsonSchema['description'];
        }

        if (isset($jsonSchema['properties']) && is_array($jsonSchema['properties'])) {
            $geminiSchema['properties'] = [];
            /** @var array<string, mixed> $properties */
            $properties = $jsonSchema['properties'];
            foreach ($properties as $name => $prop) {
                if (is_array($prop)) {
                    $geminiSchema['properties'][$name] = $this->convertToGeminiSchema($prop);
                }
            }
        }

        if (isset($jsonSchema['required'])) {
            $geminiSchema['required'] = $jsonSchema['required'];
        }

        if (isset($jsonSchema['items']) && is_array($jsonSchema['items'])) {
            $geminiSchema['items'] = $this->convertToGeminiSchema($jsonSchema['items']);
        }

        if (isset($jsonSchema['enum'])) {
            $geminiSchema['enum'] = $jsonSchema['enum'];
        }

        return $geminiSchema;
    }

    /**
     * Build the endpoint URL with model and action.
     *
     * @param string $action The API action (e.g., ':generateContent').
     * @param string|null $model Optional model override (uses config default if null).
     */
    private function buildEndpoint(string $action, ?string $model = null): string
    {
        $effectiveModel = $model ?? $this->config->defaultModel;
        return "/v1beta/models/{$effectiveModel}{$action}";
    }

    /**
     * Make a request to the Google Gemini API.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws GoogleException
     */
    private function request(string $method, string $endpoint, array $body): array
    {
        $request = $this->buildRequest($method, $endpoint, $body);

        // Execute with middleware if configured
        if ($this->hasMiddleware()) {
            $pipeline = $this->getMiddlewarePipeline();
            $response = $pipeline->execute($request, fn ($req) => $this->httpClient->request($req));
        } else {
            $response = $this->httpClient->request($request);
        }

        $data = json_decode($response->body, true);

        if (!is_array($data)) {
            throw new GoogleException(
                'Invalid JSON response from Google API',
                $response->statusCode,
                ['raw_body' => $response->body],
            );
        }

        if (!$response->isSuccess()) {
            throw GoogleException::fromApiResponse($response->statusCode, $data);
        }

        return $data;
    }

    /**
     * Build an HTTP request for the Google API.
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(string $method, string $endpoint, array $body): Request
    {
        $headers = [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $this->apiKey,
        ];

        $url = rtrim($this->config->baseUrl, '/') . $endpoint;

        return new Request(
            method: $method,
            uri: $url,
            headers: $headers,
            body: json_encode($body),
        );
    }
}
