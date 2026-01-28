<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as Psr14EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\PSR14EventDispatcher;
use SageGrids\PhpAiSdk\Event\Events\RequestStarted;
use stdClass;

final class PSR14EventDispatcherTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testImplementsEventDispatcherInterface(): void
    {
        $psr14Dispatcher = Mockery::mock(Psr14EventDispatcherInterface::class);
        $dispatcher = new PSR14EventDispatcher($psr14Dispatcher);

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    public function testDispatchDelegatesToPsr14Dispatcher(): void
    {
        $event = new stdClass();
        $event->data = 'test';

        $psr14Dispatcher = Mockery::mock(Psr14EventDispatcherInterface::class);
        $psr14Dispatcher->shouldReceive('dispatch')
            ->once()
            ->with($event)
            ->andReturn($event);

        $dispatcher = new PSR14EventDispatcher($psr14Dispatcher);
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testDispatchWithRequestStartedEvent(): void
    {
        $event = RequestStarted::create('openai', 'gpt-4o', 'generateText', ['param' => 'value']);

        $psr14Dispatcher = Mockery::mock(Psr14EventDispatcherInterface::class);
        $psr14Dispatcher->shouldReceive('dispatch')
            ->once()
            ->with($event)
            ->andReturn($event);

        $dispatcher = new PSR14EventDispatcher($psr14Dispatcher);
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals('openai', $result->provider);
        $this->assertEquals('gpt-4o', $result->model);
        $this->assertEquals('generateText', $result->operation);
    }

    public function testDispatchCanReturnModifiedEvent(): void
    {
        $originalEvent = new stdClass();
        $originalEvent->data = 'original';

        $modifiedEvent = new stdClass();
        $modifiedEvent->data = 'modified';

        $psr14Dispatcher = Mockery::mock(Psr14EventDispatcherInterface::class);
        $psr14Dispatcher->shouldReceive('dispatch')
            ->once()
            ->with($originalEvent)
            ->andReturn($modifiedEvent);

        $dispatcher = new PSR14EventDispatcher($psr14Dispatcher);
        $result = $dispatcher->dispatch($originalEvent);

        $this->assertSame($modifiedEvent, $result);
        $this->assertEquals('modified', $result->data);
    }
}
