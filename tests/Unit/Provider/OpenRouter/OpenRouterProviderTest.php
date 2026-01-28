<?php

namespace Tests\Unit\Provider\OpenRouter;

use Generator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Http\HttpClientInterface;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;
use SageGrids\PhpAiSdk\Http\StreamingResponse;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\InsufficientCreditsException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\InvalidRequestException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\OpenRouterException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\RateLimitException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\OpenRouterConfig;
use SageGrids\PhpAiSdk\Provider\OpenRouter\OpenRouterProvider;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\TextChunk;

final class OpenRouterProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private HttpClientInterface $httpClient;
    private OpenRouterProvider $provider;

    protected function setUp(): void
    {
        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->provider = new OpenRouterProvider(
            apiKey: 'test-api-key',
            httpClient: $this->httpClient,
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals('openrouter', $this->provider->getName());
    }

    public function testGetCapabilities(): void
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTextGeneration);
        $this->assertTrue($capabilities->supportsStreaming);
        $this->assertTrue($capabilities->supportsStructuredOutput);
        $this->assertTrue($capabilities->supportsToolCalling);
        $this->assertTrue($capabilities->supportsVision);
        $this->assertFalse($capabilities->supportsEmbeddings);
        $this->assertFalse($capabilities->supportsImageGeneration);
        $this->assertFalse($capabilities->supportsSpeechGeneration);
        $this->assertFalse($capabilities->supportsTranscription);
    }

    public function testGetAvailableModels(): void
    {
        $models = $this->provider->getAvailableModels();

        $this->assertContains('anthropic/claude-3.5-sonnet', $models);
        $this->assertContains('openai/gpt-4o', $models);
        $this->assertContains('google/gemini-pro-1.5', $models);
        $this->assertContains('meta-llama/llama-3.1-405b-instruct', $models);
    }

    public function testGenerateText(): void
    {
        $responseBody = [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 8,
                'total_tokens' => 18,
            ],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $this->assertEquals('POST', $request->method);
                $this->assertStringContainsString('/chat/completions', $request->uri);
                $this->assertEquals('Bearer test-api-key', $request->headers['Authorization']);

                $body = json_decode($request->body, true);
                $this->assertEquals('anthropic/claude-3.5-sonnet', $body['model']);
                $this->assertCount(1, $body['messages']);
                $this->assertEquals('user', $body['messages'][0]['role']);

                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $result = $this->provider->generateText([
            new UserMessage('Hello'),
        ]);

        $this->assertEquals('Hello! How can I help you today?', $result->text);
        $this->assertEquals(FinishReason::Stop, $result->finishReason);
        $this->assertEquals(10, $result->usage->promptTokens);
        $this->assertEquals(8, $result->usage->completionTokens);
        $this->assertEquals(18, $result->usage->totalTokens);
    }

    public function testGenerateTextWithSystemMessage(): void
    {
        $responseBody = [
            'choices' => [
                [
                    'message' => ['content' => 'Bonjour!'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 2, 'total_tokens' => 17],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertEquals('system', $body['messages'][0]['role']);
                $this->assertEquals('You are a French translator.', $body['messages'][0]['content']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $result = $this->provider->generateText(
            messages: [new UserMessage('Hello')],
            system: 'You are a French translator.',
        );

        $this->assertEquals('Bonjour!', $result->text);
    }

    public function testGenerateTextWithParameters(): void
    {
        $responseBody = [
            'choices' => [['message' => ['content' => 'Response'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertEquals(100, $body['max_tokens']);
                $this->assertEquals(0.7, $body['temperature']);
                $this->assertEquals(0.9, $body['top_p']);
                $this->assertEquals(['END', 'STOP'], $body['stop']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $this->provider->generateText(
            messages: [new UserMessage('Test')],
            maxTokens: 100,
            temperature: 0.7,
            topP: 0.9,
            stopSequences: ['END', 'STOP'],
        );
    }

    public function testGenerateTextWithTools(): void
    {
        $responseBody = [
            'choices' => [
                [
                    'message' => [
                        'content' => '',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location": "Paris"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 15, 'total_tokens' => 35],
        ];

        $tool = Tool::create(
            name: 'get_weather',
            description: 'Get the weather for a location',
            parameters: Schema::object([
                'location' => Schema::string()->description('The city name'),
            ]),
        );

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertArrayHasKey('tools', $body);
                $this->assertEquals('function', $body['tools'][0]['type']);
                $this->assertEquals('get_weather', $body['tools'][0]['function']['name']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $result = $this->provider->generateText(
            messages: [new UserMessage('What is the weather in Paris?')],
            tools: [$tool],
        );

        $this->assertTrue($result->hasToolCalls());
        $this->assertCount(1, $result->toolCalls);
        $this->assertEquals('call_123', $result->toolCalls[0]->id);
        $this->assertEquals('get_weather', $result->toolCalls[0]->name);
        $this->assertEquals(['location' => 'Paris'], $result->toolCalls[0]->arguments);
        $this->assertEquals(FinishReason::ToolCalls, $result->finishReason);
    }

    public function testStreamText(): void
    {
        $sseData = implode("\n\n", [
            'data: {"choices":[{"delta":{"content":"Hello"},"finish_reason":null}]}',
            'data: {"choices":[{"delta":{"content":" World"},"finish_reason":null}]}',
            'data: {"choices":[{"delta":{},"finish_reason":"stop"}],"usage":{"prompt_tokens":5,"completion_tokens":2,"total_tokens":7}}',
            'data: [DONE]',
        ]);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('eof')->andReturn(false, false, false, false, true);
        $stream->shouldReceive('read')->with(1024)->andReturn($sseData, '');
        $stream->shouldReceive('isReadable')->andReturn(true);
        $stream->shouldReceive('close')->once();

        $this->httpClient
            ->shouldReceive('stream')
            ->once()
            ->andReturn(new StreamingResponse($stream));

        $chunks = [];
        foreach ($this->provider->streamText([new UserMessage('Hi')]) as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextChunk::class, $chunks[0]);
        $this->assertEquals('Hello', $chunks[0]->delta);
        $this->assertEquals('Hello', $chunks[0]->text);

        $this->assertEquals(' World', $chunks[1]->delta);
        $this->assertEquals('Hello World', $chunks[1]->text);

        $this->assertTrue($chunks[2]->isComplete);
        $this->assertEquals(FinishReason::Stop, $chunks[2]->finishReason);
    }

    public function testGenerateObject(): void
    {
        $responseBody = [
            'choices' => [
                [
                    'message' => ['content' => '{"name": "John", "age": 30}'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
        ];

        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::integer(),
        ]);

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertEquals(['type' => 'json_object'], $body['response_format']);
                $this->assertStringContainsString('JSON', $body['messages'][0]['content']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $result = $this->provider->generateObject(
            messages: [new UserMessage('Generate a person')],
            schema: $schema,
        );

        $this->assertEquals(['name' => 'John', 'age' => 30], $result->object);
        $this->assertEquals('{"name": "John", "age": 30}', $result->text);
        $this->assertEquals(FinishReason::Stop, $result->finishReason);
    }

    public function testGenerateObjectThrowsOnInvalidJson(): void
    {
        $responseBody = [
            'choices' => [
                [
                    'message' => ['content' => 'not valid json'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $this->expectException(OpenRouterException::class);
        $this->expectExceptionMessage('Failed to parse JSON response');

        $this->provider->generateObject(
            messages: [new UserMessage('Test')],
            schema: Schema::object(['name' => Schema::string()]),
        );
    }

    public function testGenerateObjectThrowsOnSchemaValidationFailure(): void
    {
        $responseBody = [
            'choices' => [
                [
                    'message' => ['content' => '{"name": 123}'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $this->expectException(OpenRouterException::class);
        $this->expectExceptionMessage('Response does not match schema');

        $this->provider->generateObject(
            messages: [new UserMessage('Test')],
            schema: Schema::object(['name' => Schema::string()]),
        );
    }

    public function testAuthenticationException(): void
    {
        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(401, [], json_encode([
                'error' => ['message' => 'Invalid API key', 'type' => 'invalid_request_error'],
            ])));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->provider->generateText([new UserMessage('Test')]);
    }

    public function testRateLimitException(): void
    {
        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(429, [], json_encode([
                'error' => ['message' => 'Rate limit exceeded', 'type' => 'rate_limit_error'],
            ])));

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->provider->generateText([new UserMessage('Test')]);
    }

    public function testInvalidRequestException(): void
    {
        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(400, [], json_encode([
                'error' => ['message' => 'Invalid request', 'type' => 'invalid_request_error'],
            ])));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $this->provider->generateText([new UserMessage('Test')]);
    }

    public function testInsufficientCreditsException(): void
    {
        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(402, [], json_encode([
                'error' => ['message' => 'Insufficient credits', 'type' => 'payment_required'],
            ])));

        $this->expectException(InsufficientCreditsException::class);
        $this->expectExceptionMessage('Insufficient credits');

        $this->provider->generateText([new UserMessage('Test')]);
    }

    public function testSiteUrlAndAppNameHeaders(): void
    {
        $config = new OpenRouterConfig(
            siteUrl: 'https://mysite.com',
            appName: 'My Application',
        );

        $provider = new OpenRouterProvider(
            apiKey: 'test-key',
            httpClient: $this->httpClient,
            config: $config,
        );

        $responseBody = [
            'choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $this->assertEquals('https://mysite.com', $request->headers['HTTP-Referer']);
                $this->assertEquals('My Application', $request->headers['X-Title']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $provider->generateText([new UserMessage('Test')]);
    }

    public function testCustomBaseUrl(): void
    {
        $config = new OpenRouterConfig(baseUrl: 'https://custom.openrouter.ai/api/v1');

        $provider = new OpenRouterProvider(
            apiKey: 'test-key',
            httpClient: $this->httpClient,
            config: $config,
        );

        $responseBody = [
            'choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $this->assertStringStartsWith('https://custom.openrouter.ai/api/v1/', $request->uri);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $provider->generateText([new UserMessage('Test')]);
    }

    public function testCustomModel(): void
    {
        $config = new OpenRouterConfig(defaultModel: 'openai/gpt-4o');

        $provider = new OpenRouterProvider(
            apiKey: 'test-key',
            httpClient: $this->httpClient,
            config: $config,
        );

        $responseBody = [
            'choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertEquals('openai/gpt-4o', $body['model']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $provider->generateText([new UserMessage('Test')]);
    }
}
