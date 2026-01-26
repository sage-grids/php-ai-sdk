<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when rate limit is exceeded (HTTP 429).
 *
 * Provides information about when to retry the request.
 */
final class RateLimitException extends ProviderException
{
    /**
     * @param string $message The error message.
     * @param string $provider The provider identifier.
     * @param string|null $model The model identifier.
     * @param int|null $statusCode The HTTP status code.
     * @param array<string, mixed>|null $errorDetails Additional error details.
     * @param string|null $requestId The request ID.
     * @param int|null $retryAfterSeconds Seconds to wait before retrying.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message,
        string $provider,
        ?string $model = null,
        ?int $statusCode = 429,
        ?array $errorDetails = null,
        ?string $requestId = null,
        public readonly ?int $retryAfterSeconds = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $provider, $model, $statusCode, $errorDetails, $requestId, $code, $previous);
    }

    /**
     * Create a rate limit exception from an HTTP response.
     *
     * @param string $provider The provider identifier.
     * @param int $statusCode The HTTP status code.
     * @param array<string, mixed> $responseBody The parsed response body.
     * @param string|null $model The model identifier.
     * @param string|null $requestId The request ID.
     */
    public static function fromResponse(
        string $provider,
        int $statusCode,
        array $responseBody,
        ?string $model = null,
        ?string $requestId = null,
    ): static {
        $message = self::extractErrorMessage($responseBody);
        $retryAfter = self::extractRetryAfter($responseBody);

        return new self(
            $message,
            $provider,
            $model,
            $statusCode,
            $responseBody,
            $requestId,
            $retryAfter,
        );
    }

    /**
     * Extract retry-after value from response body.
     *
     * @param array<string, mixed> $responseBody The parsed response body.
     */
    private static function extractRetryAfter(array $responseBody): ?int
    {
        // Check common locations for retry-after information
        $error = $responseBody['error'] ?? [];
        $errorRetryAfter = is_array($error) ? ($error['retry_after'] ?? null) : null;

        $retryAfter = $responseBody['retry_after']
            ?? $errorRetryAfter
            ?? $responseBody['retryAfter']
            ?? null;

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }

    /**
     * Create an exception for requests per minute limit.
     */
    public static function requestsPerMinute(string $provider, int $limit, ?int $retryAfterSeconds = null): self
    {
        return new self(
            sprintf('Rate limit exceeded for provider "%s": %d requests per minute.', $provider, $limit),
            $provider,
            retryAfterSeconds: $retryAfterSeconds ?? 60,
        );
    }

    /**
     * Create an exception for tokens per minute limit.
     */
    public static function tokensPerMinute(string $provider, int $limit, ?int $retryAfterSeconds = null): self
    {
        return new self(
            sprintf('Token rate limit exceeded for provider "%s": %d tokens per minute.', $provider, $limit),
            $provider,
            retryAfterSeconds: $retryAfterSeconds ?? 60,
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
            'retryAfterSeconds' => $this->retryAfterSeconds,
        ]);
    }
}
