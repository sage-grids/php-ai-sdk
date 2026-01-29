<?php

namespace Tests\Unit\Provider\Google;

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
use SageGrids\PhpAiSdk\Provider\Google\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Provider\Google\Exception\GoogleException;
use SageGrids\PhpAiSdk\Provider\Google\Exception\InvalidRequestException;
use SageGrids\PhpAiSdk\Provider\Google\Exception\RateLimitException;
use SageGrids\PhpAiSdk\Provider\Google\GoogleConfig;
use SageGrids\PhpAiSdk\Provider\Google\GoogleProvider;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\TextChunk;

final class GoogleProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private HttpClientInterface $httpClient;
    private GoogleProvider $provider;

    protected function setUp(): void
    {
        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->provider = new GoogleProvider(
            apiKey: 'test-api-key',
            httpClient: $this->httpClient,
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals('google', $this->provider->getName());
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

        $this->assertContains('gemini-1.5-pro', $models);
        $this->assertContains('gemini-1.5-flash', $models);
        $this->assertContains('gemini-2.0-flash-exp', $models);
        $this->assertContains('gemini-1.0-pro', $models);
    }

    public function testGenerateText(): void
    {
        $responseBody = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'Hello! How can I help you today?']],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 8,
                'totalTokenCount' => 18,
            ],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $this->assertEquals('POST', $request->method);
                $this->assertStringContainsString('/v1beta/models/gemini-1.5-flash:generateContent', $request->uri);
                $this->assertStringNotContainsString('key=', $request->uri);
                $this->assertArrayHasKey('x-goog-api-key', $request->headers);
                $this->assertEquals('test-api-key', $request->headers['x-goog-api-key']);

                $body = json_decode($request->body, true);
                $this->assertArrayHasKey('contents', $body);
                $this->assertCount(1, $body['contents']);
                $this->assertEquals('user', $body['contents'][0]['role']);
                $this->assertEquals('Hello', $body['contents'][0]['parts'][0]['text']);

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
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'Bonjour!']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 15, 'candidatesTokenCount' => 2, 'totalTokenCount' => 17],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                // System message should be in systemInstruction field, not in contents
                $this->assertArrayHasKey('systemInstruction', $body);
                $this->assertEquals('You are a French translator.', $body['systemInstruction']['parts'][0]['text']);
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
            'candidates' => [['content' => ['parts' => [['text' => 'Response']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertEquals(100, $body['generationConfig']['maxOutputTokens']);
                $this->assertEquals(0.7, $body['generationConfig']['temperature']);
                $this->assertEquals(0.9, $body['generationConfig']['topP']);
                $this->assertEquals(['END', 'STOP'], $body['generationConfig']['stopSequences']);
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
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'functionCall' => [
                                    'name' => 'get_weather',
                                    'args' => ['location' => 'Paris'],
                                ],
                            ],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 20, 'candidatesTokenCount' => 15, 'totalTokenCount' => 35],
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
                $this->assertArrayHasKey('functionDeclarations', $body['tools'][0]);
                $this->assertEquals('get_weather', $body['tools'][0]['functionDeclarations'][0]['name']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $result = $this->provider->generateText(
            messages: [new UserMessage('What is the weather in Paris?')],
            tools: [$tool],
        );

        $this->assertTrue($result->hasToolCalls());
        $this->assertCount(1, $result->toolCalls);
        $this->assertEquals('get_weather', $result->toolCalls[0]->name);
        $this->assertEquals(['location' => 'Paris'], $result->toolCalls[0]->arguments);
    }

    public function testGenerateTextWithToolChoice(): void
    {
        $responseBody = [
            'candidates' => [['content' => ['parts' => [['text' => '']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
        ];

        $tool = Tool::create('test_tool', 'Test', Schema::object([]));

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $body = json_decode($request->body, true);
                $this->assertEquals('ANY', $body['toolConfig']['functionCallingConfig']['mode']);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $this->provider->generateText(
            messages: [new UserMessage('Test')],
            tools: [$tool],
            toolChoice: 'required',
        );
    }

    public function testStreamText(): void
    {
        $sseData = implode("\n\n", [
            'data: {"candidates":[{"content":{"parts":[{"text":"Hello"}]}}]}',
            'data: {"candidates":[{"content":{"parts":[{"text":" World"}]}}]}',
            'data: {"candidates":[{"content":{"parts":[{"text":""}]},"finishReason":"STOP"}],"usageMetadata":{"promptTokenCount":5,"candidatesTokenCount":2,"totalTokenCount":7}}',
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
            ->withArgs(function (Request $request) {
                $this->assertStringContainsString(':streamGenerateContent', $request->uri);
                $this->assertStringContainsString('alt=sse', $request->uri);
                return true;
            })
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
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => '{"name": "John", "age": 30}']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 20, 'candidatesTokenCount' => 10, 'totalTokenCount' => 30],
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
                $this->assertEquals('application/json', $body['generationConfig']['responseMimeType']);
                $this->assertArrayHasKey('responseSchema', $body['generationConfig']);
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
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'not valid json']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage('Failed to parse JSON response');

        $this->provider->generateObject(
            messages: [new UserMessage('Test')],
            schema: Schema::object(['name' => Schema::string()]),
        );
    }

    public function testGenerateObjectThrowsOnSchemaValidationFailure(): void
    {
        $responseBody = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => '{"name": 123}']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $this->expectException(GoogleException::class);
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
                'error' => ['message' => 'Invalid API key', 'status' => 'UNAUTHENTICATED'],
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
                'error' => ['message' => 'Rate limit exceeded', 'status' => 'RESOURCE_EXHAUSTED'],
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
                'error' => ['message' => 'Invalid request', 'status' => 'INVALID_ARGUMENT'],
            ])));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $this->provider->generateText([new UserMessage('Test')]);
    }

    public function testCustomBaseUrl(): void
    {
        $config = new GoogleConfig(baseUrl: 'https://custom.googleapis.com');

        $provider = new GoogleProvider(
            apiKey: 'test-key',
            httpClient: $this->httpClient,
            config: $config,
        );

        $responseBody = [
            'candidates' => [['content' => ['parts' => [['text' => 'Hi']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 1, 'totalTokenCount' => 6],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $this->assertStringStartsWith('https://custom.googleapis.com/', $request->uri);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $provider->generateText([new UserMessage('Test')]);
    }

    public function testFinishReasonMapping(): void
    {
        $testCases = [
            ['STOP', FinishReason::Stop],
            ['MAX_TOKENS', FinishReason::Length],
            ['SAFETY', FinishReason::ContentFilter],
            ['RECITATION', FinishReason::ContentFilter],
        ];

        foreach ($testCases as [$geminiReason, $expectedReason]) {
            $responseBody = [
                'candidates' => [
                    [
                        'content' => ['parts' => [['text' => 'Response']]],
                        'finishReason' => $geminiReason,
                    ],
                ],
                'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 1, 'totalTokenCount' => 6],
            ];

            $this->httpClient
                ->shouldReceive('request')
                ->once()
                ->andReturn(new Response(200, [], json_encode($responseBody)));

            $result = $this->provider->generateText([new UserMessage('Test')]);
            $this->assertEquals($expectedReason, $result->finishReason, "Failed for Gemini reason: {$geminiReason}");
        }
    }

    public function testCustomModel(): void
    {
        $config = new GoogleConfig(defaultModel: 'gemini-1.5-pro');

        $provider = new GoogleProvider(
            apiKey: 'test-key',
            httpClient: $this->httpClient,
            config: $config,
        );

        $responseBody = [
            'candidates' => [['content' => ['parts' => [['text' => 'Hi']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 1, 'totalTokenCount' => 6],
        ];

        $this->httpClient
            ->shouldReceive('request')
            ->once()
            ->withArgs(function (Request $request) {
                $this->assertStringContainsString('models/gemini-1.5-pro:', $request->uri);
                return true;
            })
            ->andReturn(new Response(200, [], json_encode($responseBody)));

        $provider->generateText([new UserMessage('Test')]);
    }
}
