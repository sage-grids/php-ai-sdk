<?php

namespace SageGrids\PhpAiSdk\Provider\OpenAI\Exception;

/**
 * Exception thrown when rate limit is exceeded (HTTP 429).
 */
final class RateLimitException extends OpenAIException
{
    /**
     * Get the retry-after value in seconds, if provided.
     */
    public function getRetryAfter(): ?int
    {
        $retryAfter = $this->errorData['retry_after'] ?? null;

        if ($retryAfter !== null) {
            return (int) $retryAfter;
        }

        return null;
    }
}
