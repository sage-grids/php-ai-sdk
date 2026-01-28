<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event;

use Psr\EventDispatcher\EventDispatcherInterface as Psr14EventDispatcherInterface;

/**
 * Adapter that wraps a PSR-14 compatible event dispatcher.
 *
 * This allows integration with any PSR-14 event dispatcher implementation
 * such as Symfony EventDispatcher, League Event, etc.
 *
 * Usage:
 * ```php
 * use Symfony\Component\EventDispatcher\EventDispatcher;
 *
 * $symfonyDispatcher = new EventDispatcher();
 * $dispatcher = new PSR14EventDispatcher($symfonyDispatcher);
 *
 * AIConfig::setEventDispatcher($dispatcher);
 * ```
 */
final class PSR14EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly Psr14EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to the wrapped PSR-14 dispatcher.
     */
    public function dispatch(object $event): object
    {
        return $this->dispatcher->dispatch($event);
    }
}
