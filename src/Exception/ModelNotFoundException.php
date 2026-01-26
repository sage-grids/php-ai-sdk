<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

/**
 * Exception thrown when the requested model is not found.
 *
 * This typically indicates an invalid model identifier or a model
 * that is not available for the given provider or account.
 */
final class ModelNotFoundException extends ProviderException
{
    /**
     * Create an exception for an invalid model identifier.
     */
    public static function invalidModel(string $provider, string $model): self
    {
        return new self(
            sprintf('Model "%s" not found for provider "%s".', $model, $provider),
            $provider,
            $model,
            statusCode: 404,
        );
    }

    /**
     * Create an exception for a deprecated model.
     */
    public static function deprecatedModel(string $provider, string $model, ?string $replacement = null): self
    {
        $message = $replacement !== null
            ? sprintf('Model "%s" has been deprecated for provider "%s". Use "%s" instead.', $model, $provider, $replacement)
            : sprintf('Model "%s" has been deprecated for provider "%s".', $model, $provider);

        return new self($message, $provider, $model, statusCode: 404);
    }

    /**
     * Create an exception for a model not available in the user's region.
     */
    public static function notAvailableInRegion(string $provider, string $model, string $region): self
    {
        return new self(
            sprintf('Model "%s" is not available in region "%s" for provider "%s".', $model, $region, $provider),
            $provider,
            $model,
            statusCode: 404,
        );
    }

    /**
     * Create an exception for a model requiring special access.
     */
    public static function accessRequired(string $provider, string $model): self
    {
        return new self(
            sprintf('Model "%s" requires special access approval for provider "%s".', $model, $provider),
            $provider,
            $model,
            statusCode: 404,
        );
    }
}
