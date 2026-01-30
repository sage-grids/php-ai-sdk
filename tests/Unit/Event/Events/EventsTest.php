<?php

declare(strict_types=1);

namespace Tests\Unit\Event\Events;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Event\Events\ErrorOccurred;
use SageGrids\PhpAiSdk\Event\Events\MemoryLimitWarning;
use SageGrids\PhpAiSdk\Event\Events\RequestCompleted;
use SageGrids\PhpAiSdk\Event\Events\RequestStarted;
use SageGrids\PhpAiSdk\Event\Events\StreamChunkReceived;
use SageGrids\PhpAiSdk\Event\Events\ToolCallCompleted;
use SageGrids\PhpAiSdk\Event\Events\ToolCallStarted;
use SageGrids\PhpAiSdk\Result\Usage;

final class EventsTest extends TestCase
{
    // RequestStarted Tests

    public function testRequestStartedCreate(): void
    {
        $event = RequestStarted::create(
            'openai',
            'gpt-4o',
            'generateText',
            ['param' => 'value'],
        );

        $this->assertEquals('openai', $event->provider);
        $this->assertEquals('gpt-4o', $event->model);
        $this->assertEquals('generateText', $event->operation);
        $this->assertEquals(['param' => 'value'], $event->parameters);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->timestamp);
    }

    public function testRequestStartedWithDefaultParameters(): void
    {
        $event = RequestStarted::create('anthropic', 'claude-3', 'streamText');

        $this->assertEquals('anthropic', $event->provider);
        $this->assertEquals('claude-3', $event->model);
        $this->assertEquals('streamText', $event->operation);
        $this->assertEquals([], $event->parameters);
    }

    public function testRequestStartedConstructor(): void
    {
        $timestamp = new DateTimeImmutable('2024-01-01 12:00:00');
        $event = new RequestStarted(
            'google',
            'gemini-pro',
            'generateObject',
            ['key' => 'value'],
            $timestamp,
        );

        $this->assertEquals('google', $event->provider);
        $this->assertEquals('gemini-pro', $event->model);
        $this->assertEquals('generateObject', $event->operation);
        $this->assertEquals(['key' => 'value'], $event->parameters);
        $this->assertSame($timestamp, $event->timestamp);
    }

    // RequestCompleted Tests

    public function testRequestCompletedCreate(): void
    {
        $startTime = microtime(true) - 0.5; // 500ms ago
        $usage = new Usage(100, 50, 150);
        $result = ['text' => 'Hello, world!'];

        $event = RequestCompleted::create(
            'openai',
            'gpt-4o',
            'generateText',
            $result,
            $startTime,
            $usage,
        );

        $this->assertEquals('openai', $event->provider);
        $this->assertEquals('gpt-4o', $event->model);
        $this->assertEquals('generateText', $event->operation);
        $this->assertSame($result, $event->result);
        $this->assertGreaterThan(0.4, $event->duration);
        $this->assertLessThan(1.0, $event->duration);
        $this->assertSame($usage, $event->usage);
    }

    public function testRequestCompletedWithNullUsage(): void
    {
        $startTime = microtime(true);
        $event = RequestCompleted::create(
            'anthropic',
            'claude-3',
            'streamText',
            'streaming result',
            $startTime,
            null,
        );

        $this->assertEquals('anthropic', $event->provider);
        $this->assertNull($event->usage);
    }

    public function testRequestCompletedConstructor(): void
    {
        $usage = new Usage(200, 100, 300);
        $event = new RequestCompleted(
            'google',
            'gemini-pro',
            'generateObject',
            ['object' => 'data'],
            1.5,
            $usage,
        );

        $this->assertEquals('google', $event->provider);
        $this->assertEquals('gemini-pro', $event->model);
        $this->assertEquals('generateObject', $event->operation);
        $this->assertEquals(['object' => 'data'], $event->result);
        $this->assertEquals(1.5, $event->duration);
        $this->assertSame($usage, $event->usage);
    }

    // StreamChunkReceived Tests

    public function testStreamChunkReceived(): void
    {
        $chunk = ['delta' => 'Hello'];
        $event = new StreamChunkReceived('openai', 'gpt-4o', $chunk, 5);

        $this->assertEquals('openai', $event->provider);
        $this->assertEquals('gpt-4o', $event->model);
        $this->assertSame($chunk, $event->chunk);
        $this->assertEquals(5, $event->chunkIndex);
    }

    public function testStreamChunkReceivedWithFirstChunk(): void
    {
        $chunk = ['delta' => 'First'];
        $event = new StreamChunkReceived('anthropic', 'claude-3', $chunk, 0);

        $this->assertEquals(0, $event->chunkIndex);
    }

    // ToolCallStarted Tests

    public function testToolCallStartedCreate(): void
    {
        $event = ToolCallStarted::create(
            'get_weather',
            ['location' => 'New York'],
        );

        $this->assertEquals('get_weather', $event->toolName);
        $this->assertEquals(['location' => 'New York'], $event->arguments);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->timestamp);
    }

    public function testToolCallStartedWithDefaultArguments(): void
    {
        $event = ToolCallStarted::create('simple_tool');

        $this->assertEquals('simple_tool', $event->toolName);
        $this->assertEquals([], $event->arguments);
    }

    public function testToolCallStartedConstructor(): void
    {
        $timestamp = new DateTimeImmutable('2024-01-01 12:00:00');
        $event = new ToolCallStarted(
            'calculate',
            ['a' => 1, 'b' => 2],
            $timestamp,
        );

        $this->assertEquals('calculate', $event->toolName);
        $this->assertEquals(['a' => 1, 'b' => 2], $event->arguments);
        $this->assertSame($timestamp, $event->timestamp);
    }

    // ToolCallCompleted Tests

    public function testToolCallCompletedCreate(): void
    {
        $startTime = microtime(true) - 0.1; // 100ms ago
        $event = ToolCallCompleted::create(
            'get_weather',
            ['location' => 'New York'],
            'Sunny, 72°F',
            $startTime,
        );

        $this->assertEquals('get_weather', $event->toolName);
        $this->assertEquals(['location' => 'New York'], $event->arguments);
        $this->assertEquals('Sunny, 72°F', $event->result);
        $this->assertGreaterThan(0.05, $event->duration);
        $this->assertLessThan(0.5, $event->duration);
    }

    public function testToolCallCompletedConstructor(): void
    {
        $event = new ToolCallCompleted(
            'calculate',
            ['expression' => '2+2'],
            4,
            0.05,
        );

        $this->assertEquals('calculate', $event->toolName);
        $this->assertEquals(['expression' => '2+2'], $event->arguments);
        $this->assertEquals(4, $event->result);
        $this->assertEquals(0.05, $event->duration);
    }

    public function testToolCallCompletedWithArrayResult(): void
    {
        $result = ['status' => 'success', 'data' => [1, 2, 3]];
        $event = new ToolCallCompleted(
            'fetch_data',
            ['id' => 123],
            $result,
            0.2,
        );

        $this->assertEquals($result, $event->result);
    }

    // ErrorOccurred Tests

    public function testErrorOccurredCreate(): void
    {
        $exception = new Exception('Something went wrong');
        $event = ErrorOccurred::create(
            $exception,
            'openai',
            'gpt-4o',
            'generateText',
        );

        $this->assertSame($exception, $event->exception);
        $this->assertEquals('openai', $event->provider);
        $this->assertEquals('gpt-4o', $event->model);
        $this->assertEquals('generateText', $event->operation);
    }

    public function testErrorOccurredWithNullContext(): void
    {
        $exception = new Exception('Unknown error');
        $event = ErrorOccurred::create($exception);

        $this->assertSame($exception, $event->exception);
        $this->assertNull($event->provider);
        $this->assertNull($event->model);
        $this->assertNull($event->operation);
    }

    public function testErrorOccurredConstructor(): void
    {
        $exception = new Exception('Test error');
        $event = new ErrorOccurred(
            $exception,
            'anthropic',
            'claude-3',
            'streamText',
        );

        $this->assertSame($exception, $event->exception);
        $this->assertEquals('anthropic', $event->provider);
        $this->assertEquals('claude-3', $event->model);
        $this->assertEquals('streamText', $event->operation);
    }

    public function testErrorOccurredWithPartialContext(): void
    {
        $exception = new Exception('Partial context error');
        $event = ErrorOccurred::create($exception, 'openai', null, 'generateObject');

        $this->assertEquals('openai', $event->provider);
        $this->assertNull($event->model);
        $this->assertEquals('generateObject', $event->operation);
    }

    // MemoryLimitWarning Tests

    public function testMemoryLimitWarningCreate(): void
    {
        $event = MemoryLimitWarning::create(80, 100, 3);

        $this->assertEquals(80, $event->currentMessageCount);
        $this->assertEquals(100, $event->maxMessages);
        $this->assertEquals(3, $event->roundtripCount);
        $this->assertEquals(80.0, $event->usagePercentage);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->timestamp);
    }

    public function testMemoryLimitWarningUsagePercentage(): void
    {
        $event = MemoryLimitWarning::create(75, 100, 2);
        $this->assertEquals(75.0, $event->usagePercentage);

        $event2 = MemoryLimitWarning::create(90, 100, 4);
        $this->assertEquals(90.0, $event2->usagePercentage);
    }

    public function testMemoryLimitWarningIsCritical(): void
    {
        $event = MemoryLimitWarning::create(85, 100, 3);
        $this->assertFalse($event->isCritical()); // Default threshold is 90

        $event2 = MemoryLimitWarning::create(95, 100, 4);
        $this->assertTrue($event2->isCritical());

        // Custom threshold
        $this->assertTrue($event->isCritical(80.0));
        $this->assertFalse($event->isCritical(90.0));
    }

    public function testMemoryLimitWarningConstructor(): void
    {
        $timestamp = new DateTimeImmutable('2024-01-01 12:00:00');
        $event = new MemoryLimitWarning(50, 100, 2, 50.0, $timestamp);

        $this->assertEquals(50, $event->currentMessageCount);
        $this->assertEquals(100, $event->maxMessages);
        $this->assertEquals(2, $event->roundtripCount);
        $this->assertEquals(50.0, $event->usagePercentage);
        $this->assertSame($timestamp, $event->timestamp);
    }
}
