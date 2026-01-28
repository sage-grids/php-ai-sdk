<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event;

/**
 * Event dispatcher interface matching PSR-14.
 *
 * This interface provides a simple contract for dispatching events
 * throughout the AI SDK lifecycle. It follows the PSR-14 specification
 * to allow integration with any PSR-14 compatible event dispatcher.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @template T of object
     * @param T $event The event to dispatch.
     * @return T The same event object, potentially modified by listeners.
     */
    public function dispatch(object $event): object;
}
