<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when the provider is unavailable (HTTP 500-504).
 *
 * This indicates a server-side error that may be temporary and retryable.
 */
final class ProviderUnavailableException extends ProviderException
{
    /**
     * Create an exception for an internal server error (500).
     */
    public static function internalError(string $provider, ?string $model = null): self
    {
        return new self(
            sprintf('Provider "%s" encountered an internal error. Please try again later.', $provider),
            $provider,
            $model,
            statusCode: 500,
        );
    }

    /**
     * Create an exception for a bad gateway error (502).
     */
    public static function badGateway(string $provider, ?string $model = null): self
    {
        return new self(
            sprintf('Provider "%s" returned a bad gateway error. Please try again later.', $provider),
            $provider,
            $model,
            statusCode: 502,
        );
    }

    /**
     * Create an exception for service unavailable (503).
     */
    public static function serviceUnavailable(string $provider, ?string $model = null): self
    {
        return new self(
            sprintf('Provider "%s" is currently unavailable. Please try again later.', $provider),
            $provider,
            $model,
            statusCode: 503,
        );
    }

    /**
     * Create an exception for gateway timeout (504).
     */
    public static function gatewayTimeout(string $provider, ?string $model = null): self
    {
        return new self(
            sprintf('Provider "%s" request timed out. Please try again later.', $provider),
            $provider,
            $model,
            statusCode: 504,
        );
    }

    /**
     * Create an exception for overloaded server.
     */
    public static function overloaded(string $provider, ?string $model = null): self
    {
        return new self(
            sprintf('Provider "%s" is currently overloaded. Please try again in a few moments.', $provider),
            $provider,
            $model,
            statusCode: 503,
        );
    }

    /**
     * Create an exception for scheduled maintenance.
     */
    public static function maintenance(string $provider, ?string $estimatedDuration = null): self
    {
        $message = $estimatedDuration !== null
            ? sprintf('Provider "%s" is undergoing scheduled maintenance. Estimated duration: %s.', $provider, $estimatedDuration)
            : sprintf('Provider "%s" is undergoing scheduled maintenance.', $provider);

        return new self($message, $provider, statusCode: 503);
    }
}
