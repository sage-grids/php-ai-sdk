<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider\OpenAI;

/**
 * Configuration specific to the OpenAI provider.
 */
final readonly class OpenAIConfig
{
    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    /**
     * @param string $baseUrl The base URL for API requests.
     * @param string|null $organization Optional organization ID.
     * @param string|null $project Optional project ID.
     * @param int $timeout Request timeout in seconds.
     * @param string $defaultModel Default model to use for text generation.
     * @param string $defaultEmbeddingModel Default model to use for embeddings.
     */
    public function __construct(
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public ?string $organization = null,
        public ?string $project = null,
        public int $timeout = 30,
        public string $defaultModel = 'gpt-4o',
        public string $defaultEmbeddingModel = 'text-embedding-3-small',
    ) {
    }
}
