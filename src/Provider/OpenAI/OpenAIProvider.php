<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\OpenAI;

use Generator;
use SageGrids\PhpAiSdk\Core\Message\Formatter\MessageFormatterInterface;
use SageGrids\PhpAiSdk\Core\Message\Formatter\OpenAIMessageFormatter;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Http\GuzzleHttpClient;
use SageGrids\PhpAiSdk\Http\HttpClientInterface;
use SageGrids\PhpAiSdk\Http\MultipartBody;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Provider\EmbeddingProviderInterface;
use SageGrids\PhpAiSdk\Provider\ImageProviderInterface;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\OpenAIException;
use SageGrids\PhpAiSdk\Provider\ProviderCapabilities;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\EmbeddingData;
use SageGrids\PhpAiSdk\Result\EmbeddingResult;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ImageData;
use SageGrids\PhpAiSdk\Result\ImageResult;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Http\Middleware\HasMiddleware;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\Usage;

/**
 * OpenAI provider implementation supporting text generation, streaming,
 * structured output, tool calling, and embeddings.
 */
final class OpenAIProvider implements TextProviderInterface, EmbeddingProviderInterface, ImageProviderInterface
{
    use HasMiddleware;
    private HttpClientInterface $httpClient;
    private OpenAIConfig $config;
    private MessageFormatterInterface $messageFormatter;

    /**
     * Available OpenAI chat models.
     */
    private const AVAILABLE_MODELS = [
        'gpt-5.4',
        'gpt-5.4-pro',
        'gpt-5.2',
        'gpt-5-mini',
        'o4-mini',
        'o3-pro',
        'o3',
        'o3-mini',
        'o1',
        'gpt-4.1',
        'gpt-4.1-mini',
        'gpt-4.1-nano',
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4-turbo',
        'gpt-4',
    ];

    /**
     * All OpenAI image generation models.
     */
    private const IMAGE_MODELS = [
        'gpt-image-1.5',
        'gpt-image-1',
        'gpt-image-1-mini',
        'chatgpt-image-latest',
        'dall-e-3',
        'dall-e-2',
    ];

    /**
     * GPT image models (gpt-image-1 family) — distinct behaviour from DALL-E.
     * - Support background transparency
     * - Support edits (no mask needed)
     * - Do NOT support variations
     * - Different allowed sizes: 1024x1024, 1536x1024, 1024x1536, auto
     */
    private const GPT_IMAGE_MODELS = [
        'gpt-image-1.5',
        'gpt-image-1',
        'gpt-image-1-mini',
        'chatgpt-image-latest',
    ];

    /**
     * Available embedding models.
     */
    private const EMBEDDING_MODELS = [
        'text-embedding-3-small',
        'text-embedding-3-large',
        'text-embedding-ada-002',
    ];

    public function __construct(
        private readonly string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ?OpenAIConfig $config = null,
        ?MessageFormatterInterface $messageFormatter = null,
    ) {
        $this->config = $config ?? new OpenAIConfig();
        $this->httpClient = $httpClient ?? new GuzzleHttpClient(
            timeout: $this->config->timeout,
        );
        $this->messageFormatter = $messageFormatter ?? new OpenAIMessageFormatter();
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTextGeneration: true,
            supportsStreaming: true,
            supportsStructuredOutput: true,
            supportsToolCalling: true,
            supportsImageGeneration: true,
            supportsSpeechGeneration: false, // Not implemented in this provider
            supportsTranscription: false, // Not implemented in this provider
            supportsEmbeddings: true,
            supportsVision: true,
        );
    }

    /**
     * @return string[]
     */
    public function getAvailableModels(): array
    {
        return array_merge(self::AVAILABLE_MODELS, self::EMBEDDING_MODELS, self::IMAGE_MODELS);
    }

    public function generateImage(
        string $prompt,
        ?string $model = null,
        string $size = '1024x1024',
        string $quality = 'standard',
        string $style = 'vivid',
        int $n = 1,
        string $responseFormat = 'url',
        ?string $background = null,
    ): ImageResult {
        $effectiveModel = $model ?? $this->config->defaultImageModel;
        $isGptModel     = in_array($effectiveModel, self::GPT_IMAGE_MODELS, true);
        $isDalle3       = $effectiveModel === 'dall-e-3';

        $requestBody = [
            'model'  => $effectiveModel,
            'prompt' => $prompt,
            'n'      => $isDalle3 ? 1 : $n, // dall-e-3 only supports n=1
            'size'   => $size,
        ];

        if ($isGptModel) {
            // GPT image models always return b64_json; response_format is not a supported parameter
            if ($background !== null) {
                $requestBody['background'] = $background;
            }
        } else {
            $requestBody['response_format'] = $responseFormat;
        }

        if ($isDalle3) {
            $requestBody['quality'] = $quality;
            $requestBody['style']   = $style;
        }

        $response = $this->request('POST', '/images/generations', $requestBody);

        $images = array_map(
            fn (array $item) => ImageData::fromArray($item),
            $response['data'] ?? [],
        );

        return new ImageResult(images: $images, rawResponse: $response);
    }

    /**
     * Edit an image using a text prompt (multipart upload).
     *
     * Supported models: gpt-image-1.5, gpt-image-1, gpt-image-1-mini, dall-e-2.
     * The $image parameter can be a file path, a URL (gpt-image-1 only), or a
     * base64 data URI (e.g. "data:image/png;base64,...").
     * The $mask parameter is optional and only meaningful for dall-e-2.
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
        $effectiveModel = $model ?? $this->config->defaultImageModel;
        $isGptModel     = in_array($effectiveModel, self::GPT_IMAGE_MODELS, true);

        $parts = [];
        $parts[] = $this->buildImagePart('image', $image);
        $parts[] = ['name' => 'prompt',           'contents' => $prompt];
        $parts[] = ['name' => 'model',            'contents' => $effectiveModel];
        $parts[] = ['name' => 'size',             'contents' => $size];
        $parts[] = ['name' => 'n',                'contents' => (string) $n];
        $parts[] = ['name' => 'response_format',  'contents' => $responseFormat];

        if ($mask !== null && !$isGptModel) {
            // mask is only supported by dall-e-2
            $parts[] = $this->buildImagePart('mask', $mask);
        }

        $response = $this->requestMultipart('POST', '/images/edits', $parts);

        $images = array_map(
            fn (array $item) => ImageData::fromArray($item),
            $response['data'] ?? [],
        );

        return new ImageResult(images: $images, rawResponse: $response);
    }

    /**
     * Create variations of an image (dall-e-2 only).
     *
     * The $image parameter must be a file path to a square PNG file under 4 MB.
     */
    public function createImageVariation(
        string $image,
        ?string $model = null,
        string $size = '1024x1024',
        int $n = 1,
        string $responseFormat = 'url',
    ): ImageResult {
        $effectiveModel = $model ?? 'dall-e-2'; // variations are dall-e-2 only

        $parts = [];
        $parts[] = $this->buildImagePart('image', $image);
        $parts[] = ['name' => 'model',            'contents' => $effectiveModel];
        $parts[] = ['name' => 'n',                'contents' => (string) $n];
        $parts[] = ['name' => 'size',             'contents' => $size];
        $parts[] = ['name' => 'response_format',  'contents' => $responseFormat];

        $response = $this->requestMultipart('POST', '/images/variations', $parts);

        $images = array_map(
            fn (array $item) => ImageData::fromArray($item),
            $response['data'] ?? [],
        );

        return new ImageResult(images: $images, rawResponse: $response);
    }

    /**
     * Build a multipart part for an image field.
     * Accepts a file path, URL string, or base64 data URI.
     *
     * @return array<string, mixed>
     */
    private function buildImagePart(string $fieldName, string $image): array
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            // URL reference — supported by gpt-image-1 family
            return ['name' => $fieldName, 'contents' => $image];
        }

        if (str_starts_with($image, 'data:')) {
            // Base64 data URI: "data:image/png;base64,<data>"
            $commaPos  = strpos($image, ',');
            $mimeMatch = [];
            preg_match('/data:([^;]+);/', $image, $mimeMatch);
            $mime     = $mimeMatch[1] ?? 'image/png';
            $ext      = explode('/', $mime)[1] ?? 'png';
            $contents = base64_decode(substr($image, $commaPos + 1));

            return ['name' => $fieldName, 'contents' => $contents, 'filename' => "$fieldName.$ext"];
        }

        // File path
        if (!file_exists($image)) {
            throw new \InvalidArgumentException("Image file not found: $image");
        }

        return ['name' => $fieldName, 'contents' => fopen($image, 'r'), 'filename' => basename($image)];
    }

    /**
     * Make a multipart/form-data request to the OpenAI API.
     *
     * @param array<int, array<string, mixed>> $parts
     * @return array<string, mixed>
     * @throws OpenAIException
     */
    private function requestMultipart(string $method, string $endpoint, array $parts): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        // Do NOT set Content-Type — Guzzle sets it automatically with the boundary for multipart

        if ($this->config->organization !== null) {
            $headers['OpenAI-Organization'] = $this->config->organization;
        }

        if ($this->config->project !== null) {
            $headers['OpenAI-Project'] = $this->config->project;
        }

        $url     = rtrim($this->config->baseUrl, '/') . $endpoint;
        $request = new Request(method: $method, uri: $url, headers: $headers, body: new MultipartBody($parts));

        if ($this->hasMiddleware()) {
            $pipeline = $this->getMiddlewarePipeline();
            $response = $pipeline->execute($request, fn ($req) => $this->httpClient->request($req));
        } else {
            $response = $this->httpClient->request($request);
        }

        $data = json_decode($response->body, true);

        if (!is_array($data)) {
            throw new OpenAIException(
                'Invalid JSON response from OpenAI API',
                $response->statusCode,
                ['raw_body' => $response->body],
            );
        }

        if (!$response->isSuccess()) {
            throw OpenAIException::fromApiResponse($response->statusCode, $data);
        }

        return $data;
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
        $requestBody = $this->buildChatRequest(
            messages: $messages,
            model: $model,
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
        ?string $model = null,
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
            model: $model,
            system: $system,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
            stream: true,
        );

        // Enable stream_options for usage in streaming
        $requestBody['stream_options'] = ['include_usage' => true];

        $request = $this->buildRequest('POST', '/chat/completions', $requestBody);
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

            // Handle usage data (sent in the final chunk with stream_options)
            if (isset($data['usage']) && is_array($data['usage'])) {
                $usage = Usage::fromArray($data['usage']);
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
                    $finalYielded = true;
                } else {
                    yield TextChunk::continue($accumulatedText, $content);
                }
            }
        }

        // Ensure we yield a final chunk if we haven't already
        if (!$finalYielded && $finishReason !== null && !$isFirst) {
            yield TextChunk::final($accumulatedText, '', $finishReason, $usage);
        }
    }

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
        $jsonSchema = $schema->toJsonSchema();
        $schemaInstruction = "You must respond with valid JSON that matches this schema:\n" . json_encode($jsonSchema, JSON_PRETTY_PRINT);

        $effectiveSystem = $system !== null
            ? $system . "\n\n" . $schemaInstruction
            : $schemaInstruction;

        $requestBody = $this->buildChatRequest(
            messages: $messages,
            model: $model,
            system: $effectiveSystem,
            maxTokens: $maxTokens,
            temperature: $temperature,
            topP: $topP,
            stopSequences: $stopSequences,
            tools: null,
            toolChoice: null,
            stream: false,
        );

        // Enable JSON mode
        $requestBody['response_format'] = ['type' => 'json_object'];

        $response = $this->request('POST', '/chat/completions', $requestBody);

        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $text = $message['content'] ?? '';
        $finishReason = FinishReason::fromString($choice['finish_reason'] ?? null);

        $usage = isset($response['usage']) && is_array($response['usage'])
            ? Usage::fromArray($response['usage'])
            : null;

        $object = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new OpenAIException(
                'Failed to parse JSON response: ' . json_last_error_msg(),
                0,
                ['raw_content' => $text],
            );
        }

        // Validate against schema
        $validation = $schema->validate($object);
        if (!$validation->isValid) {
            throw new OpenAIException(
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
        $jsonSchema = $schema->toJsonSchema();
        $schemaInstruction = "You must respond with valid JSON that matches this schema:\n" . json_encode($jsonSchema, JSON_PRETTY_PRINT);

        $effectiveSystem = $system !== null
            ? $system . "\n\n" . $schemaInstruction
            : $schemaInstruction;

        $requestBody = $this->buildChatRequest(
            messages: $messages,
            model: $model,
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
        $finalYielded = false;

        foreach ($streamingResponse->events() as $event) {
            $data = $event->data;

            if ($data === '[DONE]') {
                break;
            }

            if (!is_array($data)) {
                continue;
            }

            if (isset($data['usage']) && is_array($data['usage'])) {
                $usage = Usage::fromArray($data['usage']);
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
                            throw new OpenAIException(
                                'Response does not match schema: ' . implode(', ', $validation->errors),
                                0,
                                ['object' => $partialObject, 'errors' => $validation->errors],
                            );
                        }
                        yield ObjectChunk::final($partialObject, $accumulatedJson, $finishReason, $usage);
                        $finalYielded = true;
                    } else {
                        throw new OpenAIException(
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

    public function embed(
        string|array $input,
        ?string $model = null,
        ?int $dimensions = null,
        string $encodingFormat = 'float',
    ): EmbeddingResult {
        $model = $model ?? $this->config->defaultEmbeddingModel;
        $input = is_array($input) ? $input : [$input];

        $requestBody = [
            'model' => $model,
            'input' => $input,
            'encoding_format' => $encodingFormat,
        ];

        if ($dimensions !== null) {
            $requestBody['dimensions'] = $dimensions;
        }

        $response = $this->request('POST', '/embeddings', $requestBody);

        $embeddings = [];
        foreach ($response['data'] ?? [] as $item) {
            $embeddings[] = EmbeddingData::fromArray($item);
        }

        $usage = isset($response['usage']) && is_array($response['usage'])
            ? Usage::fromArray($response['usage'])
            : null;

        return new EmbeddingResult(
            embeddings: $embeddings,
            model: $response['model'] ?? $model,
            usage: $usage,
            rawResponse: $response,
        );
    }

    /**
     * Build the request body for a chat completion.
     *
     * @param Message[] $messages
     * @param Tool[]|null $tools
     * @return array<string, mixed>
     */
    private function buildChatRequest(
        array $messages,
        ?string $model,
        ?string $system,
        ?int $maxTokens,
        ?float $temperature,
        ?float $topP,
        ?array $stopSequences,
        ?array $tools,
        string|Tool|null $toolChoice,
        bool $stream,
    ): array {
        $formattedMessages = $this->messageFormatter->format($messages, $system);
        $effectiveModel = $model ?? $this->config->defaultModel;

        $requestBody = [
            'model' => $effectiveModel,
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
            ? Usage::fromArray($response['usage'])
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
     * Make a request to the OpenAI API.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws OpenAIException
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
            throw new OpenAIException(
                'Invalid JSON response from OpenAI API',
                $response->statusCode,
                ['raw_body' => $response->body],
            );
        }

        if (!$response->isSuccess()) {
            throw OpenAIException::fromApiResponse($response->statusCode, $data);
        }

        return $data;
    }

    /**
     * Build an HTTP request for the OpenAI API.
     *
     * @param array<string, mixed> $body
     */
    private function buildRequest(string $method, string $endpoint, array $body): Request
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->config->organization !== null) {
            $headers['OpenAI-Organization'] = $this->config->organization;
        }

        if ($this->config->project !== null) {
            $headers['OpenAI-Project'] = $this->config->project;
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
