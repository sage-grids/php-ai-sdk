<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\Google\Exception;

/**
 * Exception thrown when rate limit is exceeded (HTTP 429).
 */
final class RateLimitException extends GoogleException
{
}
