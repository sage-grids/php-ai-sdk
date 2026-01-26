<?php

namespace SageGrids\PhpAiSdk\Provider\OpenAI\Exception;

use RuntimeException;

/**
 * Base exception for OpenAI provider errors.
 */
class OpenAIException extends RuntimeException
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
        $message = $error['message'] ?? 'Unknown OpenAI API error';
        $type = $error['type'] ?? null;

        return match ($statusCode) {
            401 => new AuthenticationException($message, $statusCode, $response),
            429 => new RateLimitException($message, $statusCode, $response),
            400 => new InvalidRequestException($message, $statusCode, $response),
            404 => new NotFoundException($message, $statusCode, $response),
            500, 502, 503, 504 => new ServerException($message, $statusCode, $response),
            default => new self($message, $statusCode, $response),
        };
    }
}
