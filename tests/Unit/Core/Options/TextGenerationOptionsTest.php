<?php

namespace Tests\Unit\Core\Options;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Options\TextGenerationOptions;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;

final class TextGenerationOptionsTest extends TestCase
{
    public function testConstructWithTypedProperties(): void
    {
        $tool = Tool::create(
            name: 'test_tool',
            description: 'A test tool',
            parameters: Schema::object([]),
        );

        $options = new TextGenerationOptions(
            model: 'openai/gpt-4o',
            prompt: 'Hello, world!',
            system: 'You are helpful.',
            maxTokens: 100,
            temperature: 0.7,
            topP: 0.9,
            stopSequences: ['END'],
            tools: [$tool],
            toolChoice: 'auto',
        );

        $this->assertSame('openai/gpt-4o', $options->model);
        $this->assertSame('Hello, world!', $options->prompt);
        $this->assertSame('You are helpful.', $options->system);
        $this->assertSame(100, $options->maxTokens);
        $this->assertSame(0.7, $options->temperature);
        $this->assertSame(0.9, $options->topP);
        $this->assertSame(['END'], $options->stopSequences);
        $this->assertCount(1, $options->tools);
        $this->assertSame('auto', $options->toolChoice);
    }

    public function testFromArrayMapsAllFields(): void
    {
        $options = TextGenerationOptions::fromArray([
            'model' => 'openai/gpt-4o',
            'prompt' => 'Hello!',
            'system' => 'Be brief.',
            'maxTokens' => 50,
            'temperature' => 0.5,
            'topP' => 0.8,
            'stopSequences' => ['STOP'],
            'toolChoice' => 'none',
            'maxToolRoundtrips' => 3,
        ]);

        $this->assertSame('openai/gpt-4o', $options->model);
        $this->assertSame('Hello!', $options->prompt);
        $this->assertSame('Be brief.', $options->system);
        $this->assertSame(50, $options->maxTokens);
        $this->assertSame(0.5, $options->temperature);
        $this->assertSame(0.8, $options->topP);
        $this->assertSame(['STOP'], $options->stopSequences);
        $this->assertSame('none', $options->toolChoice);
        $this->assertSame(3, $options->maxToolRoundtrips);
    }

    public function testFromArrayWithMessages(): void
    {
        $messages = [
            new UserMessage('First message'),
            new UserMessage('Second message'),
        ];

        $options = TextGenerationOptions::fromArray([
            'model' => 'openai/gpt-4o',
            'messages' => $messages,
        ]);

        $this->assertSame($messages, $options->messages);
    }

    public function testToArrayReturnsOnlySetValues(): void
    {
        $options = new TextGenerationOptions(
            model: 'openai/gpt-4o',
            prompt: 'Hello!',
            temperature: 0.7,
        );

        $array = $options->toArray();

        $this->assertSame([
            'model' => 'openai/gpt-4o',
            'prompt' => 'Hello!',
            'temperature' => 0.7,
        ], $array);
    }

    public function testToArrayRoundTrip(): void
    {
        $original = [
            'model' => 'openai/gpt-4o',
            'prompt' => 'Test prompt',
            'system' => 'System instructions',
            'maxTokens' => 200,
            'temperature' => 0.8,
        ];

        $options = TextGenerationOptions::fromArray($original);
        $array = $options->toArray();

        $this->assertSame($original, $array);
    }

    public function testWithCallbacks(): void
    {
        $chunkCalled = false;
        $finishCalled = false;

        $options = new TextGenerationOptions(
            model: 'openai/gpt-4o',
            prompt: 'Hello!',
            onChunk: function () use (&$chunkCalled) {
                $chunkCalled = true;
            },
            onFinish: function () use (&$finishCalled) {
                $finishCalled = true;
            },
        );

        $this->assertIsCallable($options->onChunk);
        $this->assertIsCallable($options->onFinish);

        ($options->onChunk)();
        ($options->onFinish)();

        $this->assertTrue($chunkCalled);
        $this->assertTrue($finishCalled);
    }

    public function testWithToolAsToolChoice(): void
    {
        $tool = Tool::create(
            name: 'specific_tool',
            description: 'A specific tool',
            parameters: Schema::object([]),
        );

        $options = new TextGenerationOptions(
            model: 'openai/gpt-4o',
            prompt: 'Use the tool',
            tools: [$tool],
            toolChoice: $tool,
        );

        $this->assertSame($tool, $options->toolChoice);
    }
}
