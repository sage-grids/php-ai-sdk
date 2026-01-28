<?php

namespace SageGrids\PhpAiSdk\Provider\OpenRouter;

use Generator;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Http\GuzzleHttpClient;
use SageGrids\PhpAiSdk\Http\HttpClientInterface;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\OpenRouterException;
use SageGrids\PhpAiSdk\Provider\ProviderCapabilities;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\Usage;

/**
 * OpenRouter provider implementation supporting multi-model access through
 * OpenAI-compatible API format with support for various underlying providers.
 */
final class OpenRouterProvider implements TextProviderInterface
{
    private HttpClientInterface $httpClient;
    private OpenRouterConfig $config;

    /**
     * Popular models available through OpenRouter.
     * OpenRouter supports many more models - these are common examples.
     */
    private const AVAILABLE_MODELS = [
        'anthropic/claude-3.5-sonnet',
        'anthropic/claude-3-opus',
        'anthropic/claude-3-haiku',
        'openai/gpt-4o',
        'openai/gpt-4o-mini',
        'openai/gpt-4-turbo',
        'google/gemini-pro-1.5',
        'google/gemini-flash-1.5',
        'meta-llama/llama-3.1-405b-instruct',
        'meta-llama/llama-3.1-70b-instruct',
        'mistralai/mistral-large',
        'mistralai/mixtral-8x22b-instruct',
    ];

    public function __construct(
        private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?OpenRouterConfig $config = null,
    ) {
        $this->config = $config ?? new OpenRouterConfig();
        $this->httpClient = $httpClient ?? new GuzzleHttpClient(
            timeout: $this->config->timeout,
        );
    }

    public function getName(): string
    {
        return 'openrouter';
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
            supportsEmbeddings: false,
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
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): TextResult {
        $requestBody = $this->buildChatRequest(
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
            stream: false,
        );

        $response = $this->request('POST', '/chat/completions', $requestBody);

        return $this->parseTextResponse($response);
    }

    public function streamText(
        array $messages,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): Generator {
        $requestBody = $this->buildChatRequest(
            messages: $messages,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
            stream: true,
        );

        // Enable stream_options for usage in streaming (OpenRouter supports this)
        $requestBody['stream_options'] = ['include_usage' => true];

        $request = $this->buildRequest('POST', '/chat/completions', $requestBody);
        $streamingResponse = $this->httpClient->stream($request);

        $accumulatedText = '';
        $finishReason = null;
        $usage = null;
        $isFirst = true;

        foreach ($streamingResponse->events() as $event) {
            $data = $event->data;

            if ($data === '[DONE]') {
                break;
            }

            if (!is_array($data)) {
                continue;
            }

            // Handle usage data (sent in the final chunk with stream_options)
            if (isset($data['usage']) && is_array($data['usage'])) {
                $usage = $this->parseUsage($data['usage']);
            }

            $choices = $data['choices'] ?? [];
            if (empty($choices)) {
                continue;
            }

            $choice = $choices[0];
            $delta = $choice['delta'] ?? [];
            $content = $delta['content'] ?? '';

            if (isset($choice['finish_reason'])) {
                $finishReason = FinishReason::fromString($choice['finish_reason']);
            }

            if ($content !== '') {
                $accumulatedText .= $content;

                if ($isFirst) {
                    yield TextChunk::first($content);
                    $isFirst = false;
                } elseif ($finishReason !== null) {
                    yield TextChunk::final($accumulatedText, $content, $finishReason, $usage);
                } else {
                    yield TextChunk::continue($accumulatedText, $content);
                }
            }
        }

        // Ensure we yield a final chunk if we haven't already
        if ($finishReason !== null && !$isFirst) {
            yield TextChunk::final($accumulatedText, '', $finishReason, $usage);
        }
    }

    /**
     * @return ObjectResult<mixed>
     */
    public function generateObject(
        array $messages,
        Schema $schema,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
    ): ObjectResult {
        $jsonSchema = $schema->toJsonSchema();
        $schemaInstruction = "You must respond with valid JSON that matches this schema:\n" . json_encode($jsonSchema, JSON_PRETTY_PRINT);

        $effectiveSystem = $system !== null
            ? $system . "\n\n" . $schemaInstruction
            : $schemaInstruction;

        $requestBody = $this->buildChatRequest(
            messages: $messages,
            system: $effectiveSystem,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: null,
            toolChoice: null,
            stream: false,
        );

        // Enable JSON mode (supported by most models through OpenRouter)
        $requestBody['response_format'] = ['type' => 'json_object'];

        $response = $this->request('POST', '/chat/completions', $requestBody);

        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $text = $message['content'] ?? '';
        $finishReason = FinishReason::fromString($choice['finish_reason'] ?? null);

        $usage = isset($response['usage']) && is_array($response['usage'])
            ? $this->parseUsage($response['usage'])
            : null;

        $object = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new OpenRouterException(
                'Failed to parse JSON response: ' . json_last_error_msg(),
                0,
                ['raw_content' => $text],
            );
        }

        // Validate against schema
        $validation = $schema->validate($object);
        if (!$validation->isValid) {
            throw new OpenRouterException(
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
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
    ): Generator {
        $jsonSchema = $schema->toJsonSchema();
        $schemaInstruction = "You must respond with valid JSON that matches this schema:\n" . json_encode($jsonSchema, JSON_PRETTY_PRINT);

        $effectiveSystem = $system !== null
            ? $system . "\n\n" . $schemaInstruction
            : $schemaInstruction;

        $requestBody = $this->buildChatRequest(
            messages: $messages,
            system: $effectiveSystem,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: null,
            toolChoice: null,
            stream: true,
        );

        // Enable JSON mode and stream options
        $requestBody['response_format'] = ['type' => 'json_object'];
        $requestBody['stream_options'] = ['include_usage' => true];

        $request = $this->buildRequest('POST', '/chat/completions', $requestBody);
        $streamingResponse = $this->httpClient->stream($request);

        $accumulatedJson = '';
        $finishReason = null;
        $usage = null;

        foreach ($streamingResponse->events() as $event) {
            $data = $event->data;

            if ($data === '[DONE]') {
                break;
            }

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['usage']) && is_array($data['usage'])) {
                $usage = $this->parseUsage($data['usage']);
            }

            $choices = $data['choices'] ?? [];
            if (empty($choices)) {
                continue;
            }

            $choice = $choices[0];
            $delta = $choice['delta'] ?? [];
            $content = $delta['content'] ?? '';

            if (isset($choice['finish_reason'])) {
                $finishReason = FinishReason::fromString($choice['finish_reason']);
            }

            if ($content !== '') {
                $accumulatedJson .= $content;

                // Try to parse partial JSON
                $partialObject = json_decode($accumulatedJson, true);

                if ($finishReason !== null) {
                    // Final chunk - validate the complete object
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $validation = $schema->validate($partialObject);
                        if (!$validation->isValid) {
                            throw new OpenRouterException(
                                'Response does not match schema: ' . implode(', ', $validation->errors),
                                0,
                                ['object' => $partialObject, 'errors' => $validation->errors],
                            );
                        }
                        yield ObjectChunk::final($partialObject, $accumulatedJson, $finishReason, $usage);
                    } else {
                        throw new OpenRouterException(
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
        if ($finishReason !== null) {
            $finalObject = json_decode($accumulatedJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                yield ObjectChunk::final($finalObject, $accumulatedJson, $finishReason, $usage);
            }
        }
    }

    /**
     * Build the request body for a chat completion.
     *
     * @param Message[] $messages
     * @param string[]|null $stopSequences
     * @param Tool[]|null $tools
     * @return array<string, mixed>
     */
    private function buildChatRequest(
        array $messages,
        ?string $system,
        ?int $maxTokens,
        ?float $temperature,
        ?float $topP,
        ?array $stopSequences,
        ?array $tools,
        string|Tool|null $toolChoice,
        bool $stream,
    ): array {
        $formattedMessages = $this->formatMessages($messages, $system);

        $requestBody = [
            'model' => $this->config->defaultModel,
            'messages' => $formattedMessages,
        ];

        if ($maxTokens !== null) {
            $requestBody['max_tokens'] = $maxTokens;
        }

        if ($temperature !== null) {
            $requestBody['temperature'] = $temperature;
        }

        if ($topP !== null) {
            $requestBody['top_p'] = $topP;
        }

        if ($stopSequences !== null && !empty($stopSequences)) {
            $requestBody['stop'] = $stopSequences;
        }

        if ($tools !== null && !empty($tools)) {
            $requestBody['tools'] = array_map(
                fn (Tool $tool) => $tool->toOpenAIFormat(),
                $tools
            );

            if ($toolChoice !== null) {
                $requestBody['tool_choice'] = $this->formatToolChoice($toolChoice);
            }
        }

        if ($stream) {
            $requestBody['stream'] = true;
        }

        return $requestBody;
    }

    /**
     * Format messages for the OpenRouter API (OpenAI-compatible format).
     *
     * @param Message[] $messages
     * @return array<int, array<string, mixed>>
     */
    private function formatMessages(array $messages, ?string $system): array
    {
        $formatted = [];

        // Add system message if provided
        if ($system !== null) {
            $formatted[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        foreach ($messages as $message) {
            // Skip system messages from the messages array if we have a system parameter
            if ($message instanceof SystemMessage && $system !== null) {
                continue;
            }

            $formatted[] = $message->toArray();
        }

        return $formatted;
    }

    /**
     * Format the tool choice parameter.
     *
     * @return string|array<string, mixed>
     */
    private function formatToolChoice(string|Tool $toolChoice): string|array
    {
        if ($toolChoice instanceof Tool) {
            return [
                'type' => 'function',
                'function' => ['name' => $toolChoice->name],
            ];
        }

        return $toolChoice;
    }

    /**
     * Parse the response from a text generation request.
     *
     * @param array<string, mixed> $response
     */
    private function parseTextResponse(array $response): TextResult
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $text = $message['content'] ?? '';
        $finishReason = FinishReason::fromString($choice['finish_reason'] ?? null);

        $usage = isset($response['usage']) && is_array($response['usage'])
            ? $this->parseUsage($response['usage'])
            : null;

        $toolCalls = [];
        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCallData) {
                $toolCalls[] = ToolCall::fromArray($toolCallData);
            }
        }

        return new TextResult(
            text: $text,
            finishReason: $finishReason,
            usage: $usage,
            toolCalls: $toolCalls,
            rawResponse: $response,
        );
    }

    /**
     * Parse usage data from OpenRouter response.
     * OpenRouter may include additional native token counts from the underlying provider.
     *
     * @param array<string, mixed> $usageData
     */
    private function parseUsage(array $usageData): Usage
    {
        // Standard OpenAI-compatible fields
        $promptTokens = (int) ($usageData['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usageData['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usageData['total_tokens'] ?? 0);

        // OpenRouter may also provide native token counts from the underlying provider
        // These are available in native_tokens_prompt and native_tokens_completion
        // For now, we use the standard counts but the raw data is preserved in TextResult

        return new Usage(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
        );
    }

    /**
     * Make a request to the OpenRouter API.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws OpenRouterException
     */
    private function request(string $method, string $endpoint, array $body): array
    {
        $request = $this->buildRequest($method, $endpoint, $body);
        $response = $this->httpClient->request($request);

        $data = json_decode($response->body, true);

        if (!is_array($data)) {
            throw new OpenRouterException(
                'Invalid JSON response from OpenRouter API',
                $response->statusCode,
                ['raw_body' => $response->body],
            );
        }

        if (!$response->isSuccess()) {
            throw OpenRouterException::fromResponse($response->statusCode, $data);
        }

        return $data;
    }

    /**
     * Build an HTTP request for the OpenRouter API.
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(string $method, string $endpoint, array $body): Request
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        // OpenRouter-specific headers for tracking and analytics
        if ($this->config->siteUrl !== null) {
            $headers['HTTP-Referer'] = $this->config->siteUrl;
        }

        if ($this->config->appName !== null) {
            $headers['X-Title'] = $this->config->appName;
        }

        $url = rtrim($this->config->baseUrl, '/') . $endpoint;

        return new Request(
            method: $method,
            uri: $url,
            headers: $headers,
            body: json_encode($body),
        );
    }
}
