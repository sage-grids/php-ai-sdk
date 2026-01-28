<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

use Throwable;

/**
 * Event dispatched when an error occurs during an AI operation.
 *
 * This event is dispatched when an exception is thrown during request
 * processing, allowing listeners to log errors, send alerts, or perform
 * error recovery actions.
 */
final readonly class ErrorOccurred
{
    /**
     * @param Throwable $exception The exception that was thrown.
     * @param string|null $provider The provider name, if available.
     * @param string|null $model The model identifier, if available.
     * @param string|null $operation The operation type, if available.
     */
    public function __construct(
        public Throwable $exception,
        public ?string $provider,
        public ?string $model,
        public ?string $operation,
    ) {
    }

    /**
     * Create a new ErrorOccurred event.
     *
     * @param Throwable $exception The exception that occurred.
     * @param string|null $provider The provider name.
     * @param string|null $model The model identifier.
     * @param string|null $operation The operation type.
     */
    public static function create(
        Throwable $exception,
        ?string $provider = null,
        ?string $model = null,
        ?string $operation = null,
    ): self {
        return new self($exception, $provider, $model, $operation);
    }
}
