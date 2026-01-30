<?php

namespace Tests\Unit;

use Generator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Exception\InputValidationException;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\Usage;

final class AITest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private TextProviderInterface $provider;

    protected function setUp(): void
    {
        AIConfig::reset();
        ProviderRegistry::resetInstance();

        $this->provider = Mockery::mock(TextProviderInterface::class);
        $this->provider->shouldReceive('getName')->andReturn('test');

        ProviderRegistry::getInstance()->register('test', $this->provider);
    }

    protected function tearDown(): void
    {
        AIConfig::reset();
        ProviderRegistry::resetInstance();
    }

    public function testGenerateTextWithPrompt(): void
    {
        $expectedResult = new TextResult(
            text: 'Hello! How can I help?',
            finishReason: FinishReason::Stop,
            usage: new Usage(10, 5, 15),
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function (array $messages) {
                $this->assertCount(1, $messages);
                $this->assertInstanceOf(UserMessage::class, $messages[0]);
                $this->assertEquals('Hello', $messages[0]->content);
                return true;
            })
            ->andReturn($expectedResult);

        $result = AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'Hello',
        ]);

        $this->assertEquals('Hello! How can I help?', $result->text);
        $this->assertEquals(FinishReason::Stop, $result->finishReason);
    }

    public function testGenerateTextWithMessages(): void
    {
        $messages = [
            new UserMessage('First message'),
            new UserMessage('Second message'),
        ];

        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function (array $msgs) use ($messages) {
                $this->assertCount(2, $msgs);
                return true;
            })
            ->andReturn($expectedResult);

        $result = AI::generateText([
            'model' => 'test/gpt-4',
            'messages' => $messages,
        ]);

        $this->assertEquals('Response', $result->text);
    }

    public function testGenerateTextWithSystemMessage(): void
    {
        $expectedResult = new TextResult(
            text: 'Bonjour!',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function ($messages, $model, $system) {
                $this->assertEquals('You are a French translator', $system);
                return true;
            })
            ->andReturn($expectedResult);

        $result = AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'Hello',
            'system' => 'You are a French translator',
        ]);

        $this->assertEquals('Bonjour!', $result->text);
    }

    public function testGenerateTextWithParameters(): void
    {
        $expectedResult = new TextResult(text: 'Response', finishReason: FinishReason::Stop);

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function ($messages, $model, $system, $maxTokens, $temperature, $topP, $stopSequences) {
                $this->assertEquals(100, $maxTokens);
                $this->assertEquals(0.7, $temperature);
                $this->assertEquals(0.9, $topP);
                $this->assertEquals(['END'], $stopSequences);
                return true;
            })
            ->andReturn($expectedResult);

        AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'maxTokens' => 100,
            'temperature' => 0.7,
            'topP' => 0.9,
            'stopSequences' => ['END'],
        ]);
    }

    public function testGenerateTextWithTools(): void
    {
        $tool = Tool::create(
            name: 'get_weather',
            description: 'Get weather',
            parameters: Schema::object(['location' => Schema::string()]),
        );

        $expectedResult = new TextResult(
            text: 'Weather result',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function ($messages, $model, $system, $maxTokens, $temperature, $topP, $stopSequences, $tools) {
                $this->assertCount(1, $tools);
                $this->assertEquals('get_weather', $tools[0]->name);
                return true;
            })
            ->andReturn($expectedResult);

        AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'What is the weather?',
            'tools' => [$tool],
        ]);
    }

    public function testGenerateTextWithToolExecution(): void
    {
        $weatherData = ['temperature' => 72, 'condition' => 'sunny'];

        $tool = Tool::create(
            name: 'get_weather',
            description: 'Get weather',
            parameters: Schema::object(['location' => Schema::string()]),
            execute: fn($args) => json_encode($weatherData),
        );

        // First call returns tool_calls
        $firstResult = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('call_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        // Second call returns final response
        $secondResult = new TextResult(
            text: 'The weather in Paris is 72°F and sunny.',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->twice()
            ->andReturn($firstResult, $secondResult);

        $result = AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'What is the weather in Paris?',
            'tools' => [$tool],
        ]);

        $this->assertEquals('The weather in Paris is 72°F and sunny.', $result->text);
    }

    public function testGenerateTextWithMaxToolRoundtrips(): void
    {
        $tool = Tool::create(
            name: 'recursive_tool',
            description: 'A tool that keeps being called',
            parameters: Schema::object([]),
            execute: fn() => 'result',
        );

        // Create a result that always has tool calls
        $toolCallResult = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('call_1', 'recursive_tool', [])],
        );

        // The provider should be called max 3 times (1 initial + 2 max roundtrips)
        $this->provider
            ->shouldReceive('generateText')
            ->times(3)
            ->andReturn($toolCallResult);

        $result = AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'tools' => [$tool],
            'maxToolRoundtrips' => 2,
        ]);

        // Should return the last result even with tool calls
        $this->assertTrue($result->hasToolCalls());
    }

    public function testGenerateTextWithOnFinishCallback(): void
    {
        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        $callbackCalled = false;
        $callbackResult = null;

        AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'onFinish' => function ($result) use (&$callbackCalled, &$callbackResult) {
                $callbackCalled = true;
                $callbackResult = $result;
            },
        ]);

        $this->assertTrue($callbackCalled);
        $this->assertSame($expectedResult, $callbackResult);
    }

    public function testGenerateTextWithDefaultProvider(): void
    {
        AIConfig::setProvider('test/gpt-4');

        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        // No model specified, should use default
        $result = AI::generateText([
            'prompt' => 'Test',
        ]);

        $this->assertEquals('Response', $result->text);
    }

    public function testGenerateTextWithDefaults(): void
    {
        AIConfig::setDefaults([
            'temperature' => 0.5,
            'maxTokens' => 500,
        ]);

        $expectedResult = new TextResult(text: 'Response', finishReason: FinishReason::Stop);

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function ($messages, $model, $system, $maxTokens, $temperature) {
                $this->assertEquals(500, $maxTokens);
                $this->assertEquals(0.5, $temperature);
                return true;
            })
            ->andReturn($expectedResult);

        AI::generateText([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
        ]);
    }

    public function testGenerateTextThrowsOnMissingModel(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('model');

        AI::generateText([
            'prompt' => 'Test',
        ]);
    }

    public function testGenerateTextThrowsOnMissingPromptAndMessages(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('prompt/messages');

        AI::generateText([
            'model' => 'test/gpt-4',
        ]);
    }

    public function testStreamText(): void
    {
        $chunks = [
            TextChunk::first('Hello'),
            TextChunk::continue('Hello World', ' World'),
            TextChunk::final('Hello World!', '!', FinishReason::Stop),
        ];

        $generator = (function () use ($chunks) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })();

        $this->provider
            ->shouldReceive('streamText')
            ->once()
            ->andReturn($generator);

        $receivedChunks = [];
        foreach (AI::streamText(['model' => 'test/gpt-4', 'prompt' => 'Hi']) as $chunk) {
            $receivedChunks[] = $chunk;
        }

        $this->assertCount(3, $receivedChunks);
        $this->assertEquals('Hello', $receivedChunks[0]->delta);
        $this->assertEquals(' World', $receivedChunks[1]->delta);
        $this->assertTrue($receivedChunks[2]->isComplete);
    }

    public function testStreamTextWithOnChunkCallback(): void
    {
        $chunks = [
            TextChunk::first('Hello'),
            TextChunk::final('Hello', '', FinishReason::Stop),
        ];

        $generator = (function () use ($chunks) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })();

        $this->provider
            ->shouldReceive('streamText')
            ->once()
            ->andReturn($generator);

        $callbackChunks = [];
        $gen = AI::streamText([
            'model' => 'test/gpt-4',
            'prompt' => 'Hi',
            'onChunk' => function ($chunk) use (&$callbackChunks) {
                $callbackChunks[] = $chunk;
            },
        ]);

        // Consume the generator
        iterator_to_array($gen);

        $this->assertCount(2, $callbackChunks);
    }

    public function testGenerateObject(): void
    {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::integer(),
        ]);

        $expectedResult = new ObjectResult(
            object: ['name' => 'John', 'age' => 30],
            text: '{"name": "John", "age": 30}',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateObject')
            ->once()
            ->withArgs(function ($messages, $schemaArg, $model) use ($schema) {
                $this->assertEquals($schema->toJsonSchema(), $schemaArg->toJsonSchema());
                return true;
            })
            ->andReturn($expectedResult);

        $result = AI::generateObject([
            'model' => 'test/gpt-4',
            'prompt' => 'Generate a person',
            'schema' => $schema,
        ]);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result->object);
    }

    public function testGenerateObjectWithClassString(): void
    {
        $expectedResult = new ObjectResult(
            object: ['name' => 'John', 'email' => 'john@example.com'],
            text: '{"name": "John", "email": "john@example.com"}',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateObject')
            ->once()
            ->andReturn($expectedResult);

        $result = AI::generateObject([
            'model' => 'test/gpt-4',
            'prompt' => 'Generate a person',
            'schema' => PersonDTO::class,
        ]);

        $this->assertEquals('John', $result->object['name']);
    }

    public function testGenerateObjectWithSchemaContext(): void
    {
        $schema = Schema::object(['name' => Schema::string()]);

        $expectedResult = new ObjectResult(
            object: ['name' => 'John'],
            text: '{"name": "John"}',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateObject')
            ->once()
            ->withArgs(function ($messages, $schemaArg, $model, $system) {
                $this->assertStringContainsString('Person', $system);
                $this->assertStringContainsString('profile', $system);
                return true;
            })
            ->andReturn($expectedResult);

        AI::generateObject([
            'model' => 'test/gpt-4',
            'prompt' => 'Generate',
            'schema' => $schema,
            'schemaName' => 'Person',
            'schemaDescription' => 'A user profile',
        ]);
    }

    public function testGenerateObjectThrowsOnMissingSchema(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('schema');

        AI::generateObject([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
        ]);
    }

    public function testStreamObject(): void
    {
        $chunks = [
            ObjectChunk::partial(['name' => 'Jo'], '{"name": "Jo'),
            ObjectChunk::final(['name' => 'John'], '{"name": "John"}', FinishReason::Stop),
        ];

        $generator = (function () use ($chunks) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })();

        $this->provider
            ->shouldReceive('streamObject')
            ->once()
            ->andReturn($generator);

        $receivedChunks = [];
        foreach (AI::streamObject([
            'model' => 'test/gpt-4',
            'prompt' => 'Generate',
            'schema' => Schema::object(['name' => Schema::string()]),
        ]) as $chunk) {
            $receivedChunks[] = $chunk;
        }

        $this->assertCount(2, $receivedChunks);
        $this->assertFalse($receivedChunks[0]->isComplete);
        $this->assertTrue($receivedChunks[1]->isComplete);
        $this->assertEquals(['name' => 'John'], $receivedChunks[1]->delta);
    }

    public function testGenerateTextWithProviderInstance(): void
    {
        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        // Pass provider instance directly
        $result = AI::generateText([
            'model' => $this->provider,
            'prompt' => 'Test',
        ]);

        $this->assertEquals('Response', $result->text);
    }
}

/**
 * Test DTO for schema class-string testing.
 */
class PersonDTO
{
    public string $name;
    public string $email;
}
