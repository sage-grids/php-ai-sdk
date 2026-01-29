<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider;

/**
 * Configuration settings for a provider.
 */
final readonly class ProviderConfig
{
    /**
     * @param string $apiKey The API key for authentication.
     * @param string|null $baseUrl Optional custom base URL for API requests.
     * @param string|null $organization Optional organization ID (e.g., for OpenAI).
     * @param int $timeout Request timeout in seconds.
     * @param int $maxRetries Maximum number of retry attempts on failure.
     * @param array<string, mixed> $headers Additional headers to include in requests.
     * @param array<string, mixed> $options Provider-specific options.
     */
    public function __construct(
        public string $apiKey,
        public ?string $baseUrl = null,
        public ?string $organization = null,
        public int $timeout = 30,
        public int $maxRetries = 3,
        public array $headers = [],
        public array $options = [],
    ) {
    }
}
