<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when streaming operations fail.
 *
 * This exception is used for errors during SSE streaming, chunk processing,
 * and other streaming-related failures.
 */
final class StreamingException extends AIException
{
    /**
     * @param string $message The error message.
     * @param string|null $eventType The SSE event type if applicable.
     * @param string|null $lastData The last received data chunk before the error.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message,
        public readonly ?string $eventType = null,
        public readonly ?string $lastData = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for a connection failure during streaming.
     */
    public static function connectionFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Streaming connection failed: %s', $reason),
            previous: $previous,
        );
    }

    /**
     * Create an exception for an unexpected stream termination.
     */
    public static function unexpectedTermination(?string $lastData = null): self
    {
        return new self(
            'Stream terminated unexpectedly.',
            lastData: $lastData,
        );
    }

    /**
     * Create an exception for an invalid SSE event.
     */
    public static function invalidEvent(string $eventType, string $reason, ?string $data = null): self
    {
        return new self(
            sprintf('Invalid SSE event "%s": %s', $eventType, $reason),
            $eventType,
            $data,
        );
    }

    /**
     * Create an exception for chunk parsing failure.
     */
    public static function chunkParsingFailed(string $chunk, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to parse streaming chunk: %s', $reason),
            lastData: $chunk,
            previous: $previous,
        );
    }

    /**
     * Create an exception for an invalid JSON chunk.
     */
    public static function invalidJsonChunk(string $chunk, ?Throwable $previous = null): self
    {
        return new self(
            'Received invalid JSON in streaming chunk.',
            lastData: $chunk,
            previous: $previous,
        );
    }

    /**
     * Create an exception for a stream error event from the provider.
     *
     * @param array<string, mixed>|null $errorData The error data from the provider.
     */
    public static function providerError(string $message, ?array $errorData = null): self
    {
        $encodedData = null;
        if ($errorData !== null) {
            $encoded = json_encode($errorData);
            $encodedData = $encoded !== false ? $encoded : null;
        }

        return new self(
            sprintf('Provider streaming error: %s', $message),
            'error',
            $encodedData,
        );
    }

    /**
     * Create an exception for an interrupted stream.
     */
    public static function interrupted(string $reason): self
    {
        return new self(
            sprintf('Stream was interrupted: %s', $reason),
        );
    }

    /**
     * Create an exception for a stream that received no data.
     */
    public static function noData(int $timeoutSeconds): self
    {
        return new self(
            sprintf('No data received from stream within %d seconds.', $timeoutSeconds),
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
            'eventType' => $this->eventType,
            'lastData' => $this->lastData,
        ]);
    }
}
