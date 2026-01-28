<?php

namespace Tests\Unit\Testing;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Testing\AITestCase;
use SageGrids\PhpAiSdk\Testing\FakeProvider;
use SageGrids\PhpAiSdk\Testing\FakeResponse;

final class AITestCaseTest extends TestCase
{
    use AITestCase;

    protected function setUp(): void
    {
        $this->setUpAI();
    }

    protected function tearDown(): void
    {
        $this->tearDownAI();
    }

    public function testFakeCreatesProvider(): void
    {
        $fake = $this->fake();

        $this->assertInstanceOf(FakeProvider::class, $fake);
        $this->assertEquals('fake', $fake->getName());
    }

    public function testFakeWithCustomName(): void
    {
        $fake = $this->fake('custom');

        $this->assertEquals('custom', $fake->getName());
    }

    public function testFakeRegistersWithProviderRegistry(): void
    {
        $fake = $this->fake('test-provider');

        $provider = ProviderRegistry::getInstance()->get('test-provider');
        $this->assertSame($fake, $provider);
    }

    public function testFakeWithTextResponsesQueuesMutlipleResponses(): void
    {
        $fake = $this->fakeWithTextResponses(['First', 'Second', 'Third']);

        $result1 = AI::generateText(['model' => $fake, 'prompt' => '1']);
        $result2 = AI::generateText(['model' => $fake, 'prompt' => '2']);
        $result3 = AI::generateText(['model' => $fake, 'prompt' => '3']);

        $this->assertEquals('First', $result1->text);
        $this->assertEquals('Second', $result2->text);
        $this->assertEquals('Third', $result3->text);
    }

    public function testFakeWithObjectResponses(): void
    {
        $fake = $this->fakeWithObjectResponses(['name' => 'John']);
        $fake->addResponse('generateObject', FakeResponse::object(['name' => 'John']));

        $result = AI::generateObject([
            'model' => $fake,
            'prompt' => 'Generate',
            'schema' => Schema::object(['name' => Schema::string()]),
        ]);

        $this->assertEquals(['name' => 'John'], $result->object);
    }

    public function testAssertAIGeneratedPasses(): void
    {
        $result = new TextResult('Hello, world!');

        $this->assertAIGenerated($result, 'Hello, world!');
    }

    public function testAssertAIGeneratedFails(): void
    {
        $result = new TextResult('Hello, world!');

        $this->expectException(AssertionFailedError::class);
        $this->assertAIGenerated($result, 'Goodbye');
    }

    public function testAssertAIGeneratedContainsPasses(): void
    {
        $result = new TextResult('Hello, world!');

        $this->assertAIGeneratedContains($result, 'world');
    }

    public function testAssertAIGeneratedContainsFails(): void
    {
        $result = new TextResult('Hello, world!');

        $this->expectException(AssertionFailedError::class);
        $this->assertAIGeneratedContains($result, 'universe');
    }

    public function testAssertAIGeneratedObjectPasses(): void
    {
        $result = new ObjectResult(['name' => 'John'], '{}');

        $this->assertAIGeneratedObject($result, ['name' => 'John']);
    }

    public function testAssertAIGeneratedObjectHasKeysPasses(): void
    {
        $result = new ObjectResult(['name' => 'John', 'age' => 30], '{}');

        $this->assertAIGeneratedObjectHasKeys($result, ['name', 'age']);
    }

    public function testAssertAIGeneratedObjectHasKeysFails(): void
    {
        $result = new ObjectResult(['name' => 'John'], '{}');

        $this->expectException(AssertionFailedError::class);
        $this->assertAIGeneratedObjectHasKeys($result, ['name', 'email']);
    }

    public function testAssertAICompletedPasses(): void
    {
        $result = new TextResult('Done', FinishReason::Stop);

        $this->assertAICompleted($result);
    }

    public function testAssertAICompletedFails(): void
    {
        $result = new TextResult('Truncated', FinishReason::Length);

        $this->expectException(AssertionFailedError::class);
        $this->assertAICompleted($result);
    }

    public function testAssertAIHasToolCallsPasses(): void
    {
        $result = new TextResult('', FinishReason::ToolCalls, null, [
            new ToolCall('1', 'tool1', []),
            new ToolCall('2', 'tool2', []),
        ]);

        $this->assertAIHasToolCalls($result);
        $this->assertAIHasToolCalls($result, 2);
    }

    public function testAssertAIHasToolCallsFailsWhenNoToolCalls(): void
    {
        $result = new TextResult('Hello');

        $this->expectException(AssertionFailedError::class);
        $this->assertAIHasToolCalls($result);
    }

    public function testAssertAICalledToolPasses(): void
    {
        $result = new TextResult('', FinishReason::ToolCalls, null, [
            new ToolCall('1', 'get_weather', ['city' => 'Paris']),
        ]);

        $this->assertAICalledTool($result, 'get_weather');
        $this->assertAICalledTool($result, 'get_weather', ['city' => 'Paris']);
    }

    public function testAssertAICalledToolFailsWhenToolNotCalled(): void
    {
        $result = new TextResult('', FinishReason::ToolCalls, null, [
            new ToolCall('1', 'get_weather', []),
        ]);

        $this->expectException(AssertionFailedError::class);
        $this->assertAICalledTool($result, 'unknown_tool');
    }

    public function testAssertAIRequestMadePasses(): void
    {
        $fake = $this->fake();
        $fake->addTextResponse('Response');

        AI::generateText(['model' => $fake, 'prompt' => 'Test']);

        $this->assertAIRequestMade($fake, 'generateText');
    }

    public function testAssertAIRequestMadeFails(): void
    {
        $fake = $this->fake();

        $this->expectException(AssertionFailedError::class);
        $this->assertAIRequestMade($fake, 'generateText');
    }

    public function testAssertAIRequestCountPasses(): void
    {
        $fake = $this->fake();
        $fake->addTextResponse('1');
        $fake->addTextResponse('2');

        AI::generateText(['model' => $fake, 'prompt' => '1']);
        AI::generateText(['model' => $fake, 'prompt' => '2']);

        $this->assertAIRequestCount($fake, 2);
        $this->assertAIRequestCount($fake, 2, 'generateText');
    }

    public function testAssertNoAIRequestsPasses(): void
    {
        $fake = $this->fake();

        $this->assertNoAIRequests($fake);
    }

    public function testAssertNoAIRequestsFails(): void
    {
        $fake = $this->fake();
        $fake->addTextResponse('Response');

        AI::generateText(['model' => $fake, 'prompt' => 'Test']);

        $this->expectException(AssertionFailedError::class);
        $this->assertNoAIRequests($fake);
    }

    public function testAssertAIRequestContainsPasses(): void
    {
        $fake = $this->fake();
        $fake->addTextResponse('Response');

        AI::generateText(['model' => $fake, 'prompt' => 'Hello, world!']);

        $this->assertAIRequestContains($fake, 'Hello');
        $this->assertAIRequestContains($fake, 'world');
    }

    public function testAssertAIRequestParamsPasses(): void
    {
        $fake = $this->fake();
        $fake->addTextResponse('Response');

        AI::generateText([
            'model' => $fake,
            'prompt' => 'Test',
            'system' => 'Be helpful',
            'maxTokens' => 100,
            'temperature' => 0.5,
        ]);

        $this->assertAIRequestParams($fake, [
            'system' => 'Be helpful',
            'maxTokens' => 100,
            'temperature' => 0.5,
        ]);
    }
}
