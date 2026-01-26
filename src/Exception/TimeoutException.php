<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when an operation times out.
 *
 * This exception is used for connection timeouts, read timeouts,
 * and general operation timeouts.
 */
final class TimeoutException extends AIException
{
    /**
     * @param string $message The error message.
     * @param string $operation The operation that timed out.
     * @param int|float|null $timeoutSeconds The timeout duration in seconds.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message,
        public readonly string $operation = 'operation',
        public readonly int|float|null $timeoutSeconds = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for a connection timeout.
     */
    public static function connectionTimeout(string $host, int|float $timeoutSeconds): self
    {
        return new self(
            sprintf('Connection to "%s" timed out after %.1f seconds.', $host, $timeoutSeconds),
            'connection',
            $timeoutSeconds,
        );
    }

    /**
     * Create an exception for a read timeout.
     */
    public static function readTimeout(int|float $timeoutSeconds): self
    {
        return new self(
            sprintf('Read operation timed out after %.1f seconds.', $timeoutSeconds),
            'read',
            $timeoutSeconds,
        );
    }

    /**
     * Create an exception for a request timeout.
     */
    public static function requestTimeout(string $url, int|float $timeoutSeconds): self
    {
        return new self(
            sprintf('Request to "%s" timed out after %.1f seconds.', $url, $timeoutSeconds),
            'request',
            $timeoutSeconds,
        );
    }

    /**
     * Create an exception for a streaming timeout.
     */
    public static function streamingTimeout(int|float $timeoutSeconds): self
    {
        return new self(
            sprintf('Streaming operation timed out after %.1f seconds.', $timeoutSeconds),
            'streaming',
            $timeoutSeconds,
        );
    }

    /**
     * Create an exception for a general operation timeout.
     */
    public static function operationTimeout(string $operation, int|float $timeoutSeconds): self
    {
        return new self(
            sprintf('%s timed out after %.1f seconds.', ucfirst($operation), $timeoutSeconds),
            $operation,
            $timeoutSeconds,
        );
    }

    /**
     * Get structured error details for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'operation' => $this->operation,
            'timeoutSeconds' => $this->timeoutSeconds,
        ]);
    }
}
