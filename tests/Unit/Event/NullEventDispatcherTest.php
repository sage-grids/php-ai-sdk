<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Event\EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\NullEventDispatcher;
use SageGrids\PhpAiSdk\Event\Events\RequestStarted;
use stdClass;

final class NullEventDispatcherTest extends TestCase
{
    private NullEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new NullEventDispatcher();
    }

    public function testImplementsEventDispatcherInterface(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, $this->dispatcher);
    }

    public function testDispatchReturnsSameEvent(): void
    {
        $event = new stdClass();
        $event->data = 'test';

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testDispatchDoesNotModifyEvent(): void
    {
        $event = RequestStarted::create('openai', 'gpt-4o', 'generateText');

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals('openai', $result->provider);
        $this->assertEquals('gpt-4o', $result->model);
        $this->assertEquals('generateText', $result->operation);
    }

    public function testDispatchHasZeroOverhead(): void
    {
        // Dispatch multiple events rapidly to verify no significant overhead
        $events = [];
        for ($i = 0; $i < 1000; $i++) {
            $events[] = new stdClass();
        }

        $start = microtime(true);
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
        $duration = microtime(true) - $start;

        // Should complete very quickly (< 10ms for 1000 events)
        $this->assertLessThan(0.01, $duration);
    }
}
