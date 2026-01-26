<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when provider authentication fails (HTTP 401).
 *
 * This typically indicates an invalid, expired, or missing API key.
 */
final class AuthenticationException extends ProviderException
{
    /**
     * Create an exception for an invalid API key.
     */
    public static function invalidApiKey(string $provider): self
    {
        return new self(
            sprintf('Invalid API key for provider "%s". Please check your credentials.', $provider),
            $provider,
            statusCode: 401,
        );
    }

    /**
     * Create an exception for a missing API key.
     */
    public static function missingApiKey(string $provider): self
    {
        return new self(
            sprintf('API key not configured for provider "%s".', $provider),
            $provider,
            statusCode: 401,
        );
    }

    /**
     * Create an exception for an expired API key.
     */
    public static function expiredApiKey(string $provider): self
    {
        return new self(
            sprintf('API key for provider "%s" has expired. Please renew your credentials.', $provider),
            $provider,
            statusCode: 401,
        );
    }

    /**
     * Create an exception for insufficient permissions.
     */
    public static function insufficientPermissions(string $provider, ?string $model = null): self
    {
        $message = $model !== null
            ? sprintf('Insufficient permissions to access model "%s" on provider "%s".', $model, $provider)
            : sprintf('Insufficient permissions for provider "%s".', $provider);

        return new self($message, $provider, $model, statusCode: 401);
    }
}
