<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\Google\Exception;

use SageGrids\PhpAiSdk\Exception\ProviderException;

/**
 * Base exception for Google Gemini provider errors.
 *
 * Extends the SDK's ProviderException for unified error handling across providers.
 */
class GoogleException extends ProviderException
{
    private const PROVIDER_NAME = 'google';

    /**
     * @param array<string, mixed> $errorData Raw error data from the API response.
     */
    public function __construct(
        string $message,
        int $code = 0,
        public readonly array $errorData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            provider: self::PROVIDER_NAME,
            model: null,
            statusCode: $code > 0 ? $code : null,
            errorDetails: $errorData,
            requestId: null,
            code: $code,
            previous: $previous,
        );
    }

    /**
     * Create an exception from an API error response.
     *
     * @param int $statusCode HTTP status code.
     * @param array<string, mixed> $response Parsed response body.
     */
    public static function fromApiResponse(int $statusCode, array $response): self
    {
        /** @var array<string, mixed> $error */
        $error = is_array($response['error'] ?? null) ? $response['error'] : [];
        /** @var string $message */
        $message = is_string($error['message'] ?? null) ? $error['message'] : 'Unknown Google API error';

        return match ($statusCode) {
            401, 403 => new AuthenticationException($message, $statusCode, $response),
            429 => new RateLimitException($message, $statusCode, $response),
            400 => self::handleBadRequest($message, $response),
            404 => new NotFoundException($message, $statusCode, $response),
            500, 502, 503, 504 => new ServerException($message, $statusCode, $response),
            default => new self($message, $statusCode, $response),
        };
    }

    /**
     * Handle 400 Bad Request with special cases for safety blocks.
     *
     * @param array<string, mixed> $response
     */
    private static function handleBadRequest(string $message, array $response): self
    {
        // Check if this is a safety-related error
        if (str_contains(strtolower($message), 'safety') ||
            str_contains(strtolower($message), 'blocked')) {
            return new SafetyException($message, 400, $response);
        }

        return new InvalidRequestException($message, 400, $response);
    }
}
