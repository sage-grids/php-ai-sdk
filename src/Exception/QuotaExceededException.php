<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

/**
 * Exception thrown when the account quota is exceeded.
 *
 * This typically indicates that the billing limit has been reached
 * or the account has insufficient credits.
 */
final class QuotaExceededException extends ProviderException
{
    /**
     * Create an exception for insufficient credits.
     */
    public static function insufficientCredits(string $provider): self
    {
        return new self(
            sprintf('Insufficient credits for provider "%s". Please add credits to your account.', $provider),
            $provider,
            statusCode: 402,
        );
    }

    /**
     * Create an exception for exceeded billing limit.
     */
    public static function billingLimitExceeded(string $provider): self
    {
        return new self(
            sprintf('Billing limit exceeded for provider "%s". Please increase your limit.', $provider),
            $provider,
            statusCode: 402,
        );
    }

    /**
     * Create an exception for plan limit exceeded.
     */
    public static function planLimitExceeded(string $provider, string $planName): self
    {
        return new self(
            sprintf('Plan limit exceeded for provider "%s" on plan "%s". Please upgrade your plan.', $provider, $planName),
            $provider,
            statusCode: 402,
        );
    }
}
