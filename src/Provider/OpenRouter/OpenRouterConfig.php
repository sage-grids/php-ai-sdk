<?php

namespace SageGrids\PhpAiSdk\Provider\OpenRouter;

/**
 * Configuration specific to the OpenRouter provider.
 */
final readonly class OpenRouterConfig
{
    public const DEFAULT_BASE_URL = 'https://openrouter.ai/api/v1';

    /**
     * @param string $baseUrl The base URL for API requests.
     * @param string|null $siteUrl Your site URL for OpenRouter tracking (HTTP-Referer header).
     * @param string|null $appName Your app name for OpenRouter tracking (X-Title header).
     * @param int $timeout Request timeout in seconds.
     * @param string $defaultModel Default model to use for text generation.
     */
    public function __construct(
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public ?string $siteUrl = null,
        public ?string $appName = null,
        public int $timeout = 30,
        public string $defaultModel = 'anthropic/claude-3.5-sonnet',
    ) {
    }
}
