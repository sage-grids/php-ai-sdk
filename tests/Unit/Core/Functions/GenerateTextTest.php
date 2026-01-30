<?php

namespace Tests\Unit\Core\Functions;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Core\Functions\GenerateText;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Exception\InputValidationException;
use SageGrids\PhpAiSdk\Exception\MemoryLimitExceededException;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;

final class GenerateTextTest extends TestCase
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

    public function testCreateAndExecute(): void
    {
        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        $function = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Hello',
        ]);

        $result = $function->execute();

        $this->assertEquals('Response', $result->text);
    }

    public function testConvertsPromptToUserMessage(): void
    {
        $expectedResult = new TextResult(text: 'Response', finishReason: FinishReason::Stop);

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function (array $messages) {
                $this->assertCount(1, $messages);
                $this->assertInstanceOf(UserMessage::class, $messages[0]);
                $this->assertEquals('My prompt', $messages[0]->content);
                return true;
            })
            ->andReturn($expectedResult);

        GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'My prompt',
        ])->execute();
    }

    public function testMergesPromptWithMessages(): void
    {
        $existingMessage = new UserMessage('Existing');

        $expectedResult = new TextResult(text: 'Response', finishReason: FinishReason::Stop);

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->withArgs(function (array $messages) {
                // Prompt should be prepended
                $this->assertCount(2, $messages);
                $this->assertEquals('Prompt', $messages[0]->content);
                $this->assertEquals('Existing', $messages[1]->content);
                return true;
            })
            ->andReturn($expectedResult);

        GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Prompt',
            'messages' => [$existingMessage],
        ])->execute();
    }

    public function testToolExecutionLoop(): void
    {
        $tool = Tool::create(
            name: 'calculator',
            description: 'Calculate',
            parameters: Schema::object(['expression' => Schema::string()]),
            execute: fn($args) => '42',
        );

        // First call triggers tool use
        $firstResult = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('call_1', 'calculator', ['expression' => '6 * 7'])],
        );

        // Second call returns final response
        $secondResult = new TextResult(
            text: 'The answer is 42.',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->twice()
            ->andReturn($firstResult, $secondResult);

        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'What is 6 * 7?',
            'tools' => [$tool],
        ])->execute();

        $this->assertEquals('The answer is 42.', $result->text);
    }

    public function testToolNotFoundInLoop(): void
    {
        // Tool in provider response but not in our tools array
        $firstResult = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('call_1', 'unknown_tool', [])],
        );

        // Provider will get error message and respond
        $secondResult = new TextResult(
            text: 'I cannot use that tool.',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->twice()
            ->andReturn($firstResult, $secondResult);

        // Provide a different tool
        $tool = Tool::create('other_tool', 'Other', Schema::object([]), fn() => 'result');

        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'tools' => [$tool],
        ])->execute();

        $this->assertEquals('I cannot use that tool.', $result->text);
    }

    public function testMaxToolRoundtripsLimit(): void
    {
        $tool = Tool::create(
            name: 'looping_tool',
            description: 'Loops',
            parameters: Schema::object([]),
            execute: fn() => 'loop',
        );

        $loopingResult = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('call_1', 'looping_tool', [])],
        );

        // Should be called exactly 2 times (initial + 1 roundtrip when maxToolRoundtrips=1)
        $this->provider
            ->shouldReceive('generateText')
            ->times(2)
            ->andReturn($loopingResult);

        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'tools' => [$tool],
            'maxToolRoundtrips' => 1,
        ])->execute();

        // Returns the last result with tool calls
        $this->assertTrue($result->hasToolCalls());
    }

    public function testNoToolExecutionWhenToolChoiceNone(): void
    {
        $tool = Tool::create('tool', 'Tool', Schema::object([]), fn() => 'result');

        $resultWithCalls = new TextResult(
            text: 'I would call a tool',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('call_1', 'tool', [])],
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($resultWithCalls);

        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'tools' => [$tool],
            'toolChoice' => 'none',
        ])->execute();

        // Should return immediately, not execute tools
        $this->assertTrue($result->hasToolCalls());
    }

    public function testThrowsOnMissingModel(): void
    {
        $this->expectException(InputValidationException::class);
        $this->expectExceptionMessage('model');

        GenerateText::create([
            'prompt' => 'Test',
        ]);
    }

    public function testThrowsOnMissingPromptAndMessages(): void
    {
        $this->expectException(InputValidationException::class);

        GenerateText::create([
            'model' => 'test/gpt-4',
        ]);
    }

    public function testMaxMessagesLimitExceeded(): void
    {
        $tool = Tool::create(
            name: 'chatty_tool',
            description: 'Creates many messages',
            parameters: Schema::object([]),
            execute: fn() => 'result',
        );

        // Each tool call adds 2 messages (assistant + tool response)
        // With maxMessages=5 and starting with 1 message, should fail after 2 roundtrips
        $loopingResult = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('call_1', 'chatty_tool', [])],
        );

        $this->provider
            ->shouldReceive('generateText')
            ->andReturn($loopingResult);

        $this->expectException(MemoryLimitExceededException::class);
        $this->expectExceptionMessage('Message limit exceeded');

        GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'tools' => [$tool],
            'maxMessages' => 5,
            'maxToolRoundtrips' => 100, // High enough to hit message limit first
        ])->execute();
    }

    public function testMaxMessagesDefaultsFromAIConfig(): void
    {
        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        // Set a custom default
        AIConfig::setMaxMessages(50);

        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
        ])->execute();

        $this->assertEquals('Response', $result->text);

        // Verify the default is respected (reset and check)
        AIConfig::reset();
        $this->assertEquals(100, AIConfig::getMaxMessages());
    }

    public function testMaxMessagesZeroIsCoercedToOne(): void
    {
        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        // maxMessages=0 should be coerced to 1, so with 1 message it should work
        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'maxMessages' => 0, // Invalid, will be coerced to 1
        ])->execute();

        $this->assertEquals('Response', $result->text);
    }

    public function testMaxMessagesNegativeIsCoercedToOne(): void
    {
        $expectedResult = new TextResult(
            text: 'Response',
            finishReason: FinishReason::Stop,
        );

        $this->provider
            ->shouldReceive('generateText')
            ->once()
            ->andReturn($expectedResult);

        // Negative maxMessages should be coerced to 1
        $result = GenerateText::create([
            'model' => 'test/gpt-4',
            'prompt' => 'Test',
            'maxMessages' => -10, // Invalid, will be coerced to 1
        ])->execute();

        $this->assertEquals('Response', $result->text);
    }
}
