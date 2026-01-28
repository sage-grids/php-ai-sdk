<?php

namespace Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Testing\RecordedRequest;

final class RecordedRequestTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $messages = [new UserMessage('Hello')];
        $schema = Schema::object(['name' => Schema::string()]);
        $tools = [
            Tool::create('test_tool', 'Test', Schema::object([])),
        ];

        $request = new RecordedRequest(
            operation: 'generateText',
            messages: $messages,
            system: 'System prompt',
            maxTokens: 100,
            temperature: 0.7,
            topP: 0.9,
            stopSequences: ['END'],
            tools: $tools,
            toolChoice: 'auto',
            schema: $schema,
            extraParams: ['custom' => 'value'],
            timestamp: 1234567890.123,
        );

        $this->assertEquals('generateText', $request->operation);
        $this->assertSame($messages, $request->messages);
        $this->assertEquals('System prompt', $request->system);
        $this->assertEquals(100, $request->maxTokens);
        $this->assertEquals(0.7, $request->temperature);
        $this->assertEquals(0.9, $request->topP);
        $this->assertEquals(['END'], $request->stopSequences);
        $this->assertSame($tools, $request->tools);
        $this->assertEquals('auto', $request->toolChoice);
        $this->assertSame($schema, $request->schema);
        $this->assertEquals(['custom' => 'value'], $request->extraParams);
        $this->assertEquals(1234567890.123, $request->timestamp);
    }

    public function testHasMessageContentFindsContent(): void
    {
        $request = new RecordedRequest(
            operation: 'generateText',
            messages: [
                new UserMessage('Hello, world!'),
                new UserMessage('How are you?'),
            ],
        );

        $this->assertTrue($request->hasMessageContent('Hello'));
        $this->assertTrue($request->hasMessageContent('world'));
        $this->assertTrue($request->hasMessageContent('How are you'));
        $this->assertFalse($request->hasMessageContent('Goodbye'));
    }

    public function testHasToolFindsTool(): void
    {
        $tools = [
            Tool::create('weather', 'Get weather', Schema::object([])),
            Tool::create('calculator', 'Calculate', Schema::object([])),
        ];

        $request = new RecordedRequest(
            operation: 'generateText',
            tools: $tools,
        );

        $this->assertTrue($request->hasTool('weather'));
        $this->assertTrue($request->hasTool('calculator'));
        $this->assertFalse($request->hasTool('unknown'));
    }

    public function testHasToolReturnsFalseWhenNoTools(): void
    {
        $request = new RecordedRequest(operation: 'generateText');

        $this->assertFalse($request->hasTool('any'));
    }

    public function testGetFirstMessageContent(): void
    {
        $request = new RecordedRequest(
            operation: 'generateText',
            messages: [
                new UserMessage('First message'),
                new UserMessage('Second message'),
            ],
        );

        $this->assertEquals('First message', $request->getFirstMessageContent());
    }

    public function testGetFirstMessageContentReturnsNullWhenEmpty(): void
    {
        $request = new RecordedRequest(operation: 'generateText');

        $this->assertNull($request->getFirstMessageContent());
    }

    public function testToArrayConvertsToArray(): void
    {
        $tool = Tool::create('test', 'Test', Schema::object([]));
        $schema = Schema::object(['name' => Schema::string()]);

        $request = new RecordedRequest(
            operation: 'generateObject',
            messages: [new UserMessage('Generate')],
            system: 'System',
            maxTokens: 100,
            temperature: 0.5,
            topP: 0.9,
            stopSequences: ['STOP'],
            tools: [$tool],
            toolChoice: 'auto',
            schema: $schema,
            extraParams: ['key' => 'value'],
            timestamp: 1000.0,
        );

        $array = $request->toArray();

        $this->assertEquals('generateObject', $array['operation']);
        $this->assertCount(1, $array['messages']);
        $this->assertEquals('System', $array['system']);
        $this->assertEquals(100, $array['maxTokens']);
        $this->assertEquals(0.5, $array['temperature']);
        $this->assertEquals(0.9, $array['topP']);
        $this->assertEquals(['STOP'], $array['stopSequences']);
        $this->assertEquals(['test'], $array['tools']);
        $this->assertEquals('auto', $array['toolChoice']);
        $this->assertIsArray($array['schema']);
        $this->assertEquals(['key' => 'value'], $array['extraParams']);
        $this->assertEquals(1000.0, $array['timestamp']);
    }

    public function testToArrayWithToolAsToolChoice(): void
    {
        $tool = Tool::create('specific_tool', 'A specific tool', Schema::object([]));

        $request = new RecordedRequest(
            operation: 'generateText',
            toolChoice: $tool,
        );

        $array = $request->toArray();

        $this->assertEquals('specific_tool', $array['toolChoice']);
    }
}
