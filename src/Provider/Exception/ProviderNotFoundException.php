<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\Exception;

use RuntimeException;

/**
 * Exception thrown when a provider is not found in the registry.
 */
final class ProviderNotFoundException extends RuntimeException
{
    public function __construct(string $providerName)
    {
        parent::__construct("Provider '{$providerName}' not found in registry.");
    }
}
