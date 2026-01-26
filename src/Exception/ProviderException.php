<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when a provider operation fails.
 *
 * This exception provides detailed context about provider errors including
 * the provider name, model, HTTP status code, and error details from the API.
 *
 * @phpstan-consistent-constructor
 */
class ProviderException extends AIException
{
    /**
     * @param string $message The error message.
     * @param string $provider The provider identifier (e.g., 'openai', 'anthropic').
     * @param string|null $model The model identifier if applicable.
     * @param int|null $statusCode The HTTP status code from the provider API.
     * @param array<string, mixed>|null $errorDetails Additional error details from the provider.
     * @param string|null $requestId The request ID from the provider for debugging.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception chaining.
     */
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly ?int $statusCode = null,
        public readonly ?array $errorDetails = null,
        public readonly ?string $requestId = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a provider exception from an HTTP response.
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

        return match ($statusCode) {
            401 => new AuthenticationException($message, $provider, $model, $statusCode, $responseBody, $requestId),
            429 => RateLimitException::fromResponse($provider, $statusCode, $responseBody, $model, $requestId),
            402 => new QuotaExceededException($message, $provider, $model, $statusCode, $responseBody, $requestId),
            404 => new ModelNotFoundException($message, $provider, $model, $statusCode, $responseBody, $requestId),
            500, 502, 503, 504 => new ProviderUnavailableException($message, $provider, $model, $statusCode, $responseBody, $requestId),
            default => new static($message, $provider, $model, $statusCode, $responseBody, $requestId, $statusCode),
        };
    }

    /**
     * Create a provider exception for a general error.
     */
    public static function forProvider(
        string $provider,
        string $message,
        ?string $model = null,
        ?Throwable $previous = null,
    ): static {
        return new static($message, $provider, $model, previous: $previous);
    }

    /**
     * Extract the error message from a response body.
     *
     * @param array<string, mixed> $responseBody The parsed response body.
     */
    protected static function extractErrorMessage(array $responseBody): string
    {
        // Try common error message locations
        $error = $responseBody['error'] ?? null;

        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
            return $error['message'];
        }

        if (isset($responseBody['message']) && is_string($responseBody['message'])) {
            return $responseBody['message'];
        }

        if (is_string($error)) {
            return $error;
        }

        return 'Unknown provider error';
    }

    /**
     * Check if this exception represents a retryable error.
     */
    public function isRetryable(): bool
    {
        return in_array($this->statusCode, [429, 500, 502, 503, 504], true);
    }

    /**
     * Get structured error details for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'provider' => $this->provider,
            'model' => $this->model,
            'statusCode' => $this->statusCode,
            'errorDetails' => $this->errorDetails,
            'requestId' => $this->requestId,
            'retryable' => $this->isRetryable(),
        ]);
    }
}
