<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\OpenRouter\Exception;

use RuntimeException;

/**
 * Base exception for OpenRouter provider errors.
 */
class OpenRouterException extends RuntimeException
{
    /**
     * @param array<string, mixed> $errorData Raw error data from the API response.
     */
    public function __construct(
        string $message,
        int $code = 0,
        public readonly array $errorData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception from an API error response.
     *
     * @param int $statusCode HTTP status code.
     * @param array<string, mixed> $response Parsed response body.
     */
    public static function fromResponse(int $statusCode, array $response): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown OpenRouter API error';

        return match ($statusCode) {
            401 => new AuthenticationException($message, $statusCode, $response),
            429 => new RateLimitException($message, $statusCode, $response),
            400 => new InvalidRequestException($message, $statusCode, $response),
            402 => new InsufficientCreditsException($message, $statusCode, $response),
            404 => new NotFoundException($message, $statusCode, $response),
            408 => new TimeoutException($message, $statusCode, $response),
            500, 502, 503, 504 => new ServerException($message, $statusCode, $response),
            default => new self($message, $statusCode, $response),
        };
    }
}
