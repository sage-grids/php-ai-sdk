<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\Google;

/**
 * Configuration specific to the Google Gemini provider.
 */
final readonly class GoogleConfig
{
    public const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com';

    /**
     * @param string $baseUrl The base URL for API requests.
     * @param int $timeout Request timeout in seconds.
     * @param string $defaultModel Default model to use for text generation.
     */
    public function __construct(
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public int $timeout = 30,
        public string $defaultModel = 'gemini-1.5-flash',
    ) {
    }
}
