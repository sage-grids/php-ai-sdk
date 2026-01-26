<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Exception;
use Throwable;

/**
 * Base exception class for all AI SDK exceptions.
 *
 * This exception serves as the root of the exception hierarchy,
 * providing common functionality for error context preservation.
 *
 * @phpstan-consistent-constructor
 */
class AIException extends Exception
{
    /**
     * @param string $message The error message.
     * @param int $code The error code.
     * @param Throwable|null $previous The previous throwable used for exception chaining.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception with a formatted message.
     */
    public static function create(string $message, int $code = 0, ?Throwable $previous = null): static
    {
        return new static($message, $code, $previous);
    }

    /**
     * Create an exception from a previous exception with an additional context message.
     */
    public static function fromPrevious(string $contextMessage, Throwable $previous): static
    {
        return new static(
            sprintf('%s: %s', $contextMessage, $previous->getMessage()),
            (int) $previous->getCode(),
            $previous,
        );
    }

    /**
     * Get the full exception chain as an array.
     *
     * @return array<int, array{class: string, message: string, code: int, file: string, line: int}>
     */
    public function getExceptionChain(): array
    {
        $chain = [];
        $exception = $this;

        while ($exception !== null) {
            $chain[] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
            $exception = $exception->getPrevious();
        }

        return $chain;
    }

    /**
     * Get structured error details for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'chain' => $this->getExceptionChain(),
        ];
    }
}
