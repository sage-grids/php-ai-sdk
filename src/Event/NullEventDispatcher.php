<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event;

/**
 * No-op event dispatcher implementation.
 *
 * This dispatcher does nothing when events are dispatched, providing
 * zero overhead when event handling is not configured. This is the
 * default dispatcher used by the AI SDK.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    /**
     * {@inheritDoc}
     *
     * This implementation does nothing and simply returns the event unchanged.
     */
    public function dispatch(object $event): object
    {
        return $event;
    }
}
