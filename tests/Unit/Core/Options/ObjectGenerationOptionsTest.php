<?php

namespace Tests\Unit\Core\Options;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Options\ObjectGenerationOptions;
use SageGrids\PhpAiSdk\Core\Schema\Schema;

final class ObjectGenerationOptionsTest extends TestCase
{
    public function testConstructWithTypedProperties(): void
    {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::integer(),
        ]);

        $options = new ObjectGenerationOptions(
            model: 'openai/gpt-4o',
            schema: $schema,
            prompt: 'Generate a user',
            system: 'You generate structured data.',
            maxTokens: 100,
            temperature: 0.7,
            topP: 0.9,
            stopSequences: ['END'],
        );

        $this->assertSame('openai/gpt-4o', $options->model);
        $this->assertSame($schema, $options->schema);
        $this->assertSame('Generate a user', $options->prompt);
        $this->assertSame('You generate structured data.', $options->system);
        $this->assertSame(100, $options->maxTokens);
        $this->assertSame(0.7, $options->temperature);
        $this->assertSame(0.9, $options->topP);
        $this->assertSame(['END'], $options->stopSequences);
    }

    public function testFromArrayMapsAllFields(): void
    {
        $schema = Schema::object([
            'title' => Schema::string(),
        ]);

        $options = ObjectGenerationOptions::fromArray([
            'model' => 'openai/gpt-4o',
            'schema' => $schema,
            'prompt' => 'Generate data',
            'system' => 'Be structured.',
            'maxTokens' => 50,
            'temperature' => 0.5,
            'topP' => 0.8,
            'stopSequences' => ['STOP'],
        ]);

        $this->assertSame('openai/gpt-4o', $options->model);
        $this->assertSame($schema, $options->schema);
        $this->assertSame('Generate data', $options->prompt);
        $this->assertSame('Be structured.', $options->system);
        $this->assertSame(50, $options->maxTokens);
        $this->assertSame(0.5, $options->temperature);
        $this->assertSame(0.8, $options->topP);
        $this->assertSame(['STOP'], $options->stopSequences);
    }

    public function testFromArrayWithMessages(): void
    {
        $messages = [
            new UserMessage('First message'),
            new UserMessage('Second message'),
        ];

        $options = ObjectGenerationOptions::fromArray([
            'model' => 'openai/gpt-4o',
            'schema' => Schema::object([]),
            'messages' => $messages,
        ]);

        $this->assertSame($messages, $options->messages);
    }

    public function testFromArrayWithClassStringSchema(): void
    {
        $options = ObjectGenerationOptions::fromArray([
            'model' => 'openai/gpt-4o',
            'schema' => \stdClass::class,
            'prompt' => 'Generate data',
        ]);

        $this->assertSame(\stdClass::class, $options->schema);
    }

    public function testToArrayReturnsOnlySetValues(): void
    {
        $schema = Schema::object([]);

        $options = new ObjectGenerationOptions(
            model: 'openai/gpt-4o',
            schema: $schema,
            prompt: 'Hello!',
            temperature: 0.7,
        );

        $array = $options->toArray();

        $this->assertSame([
            'model' => 'openai/gpt-4o',
            'schema' => $schema,
            'prompt' => 'Hello!',
            'temperature' => 0.7,
        ], $array);
    }

    public function testToArrayRoundTrip(): void
    {
        $schema = Schema::object([
            'data' => Schema::string(),
        ]);

        $original = [
            'model' => 'openai/gpt-4o',
            'schema' => $schema,
            'prompt' => 'Test prompt',
            'system' => 'System instructions',
            'maxTokens' => 200,
            'temperature' => 0.8,
        ];

        $options = ObjectGenerationOptions::fromArray($original);
        $array = $options->toArray();

        $this->assertSame($original, $array);
    }

    public function testWithCallbacks(): void
    {
        $chunkCalled = false;
        $finishCalled = false;

        $options = new ObjectGenerationOptions(
            model: 'openai/gpt-4o',
            schema: Schema::object([]),
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
}
