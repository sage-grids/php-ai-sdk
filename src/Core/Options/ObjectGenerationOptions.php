<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Options;

use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;

/**
 * Options for structured object generation requests.
 *
 * This DTO provides type-safe options for object generation operations.
 * It maintains backwards compatibility with array-based options.
 */
final readonly class ObjectGenerationOptions
{
    /**
     * @param string|ProviderInterface|null $model The model identifier (e.g., 'openai/gpt-4o') or provider instance.
     * @param Schema|class-string|null $schema The schema for the object to generate.
     * @param string|null $prompt The user prompt text.
     * @param Message[]|null $messages Array of Message objects for multi-turn conversations.
     * @param string|null $system The system prompt/instructions.
     * @param int|null $maxTokens Maximum number of tokens to generate.
     * @param float|null $temperature Sampling temperature (0.0 to 2.0).
     * @param float|null $topP Top-p (nucleus) sampling parameter.
     * @param string[]|null $stopSequences Array of sequences that stop generation.
     * @param callable|null $onChunk Callback for streaming chunks.
     * @param callable|null $onFinish Callback when generation completes.
     */
    public function __construct(
        public string|ProviderInterface|null $model = null,
        public Schema|string|null $schema = null,
        public ?string $prompt = null,
        public ?array $messages = null,
        public ?string $system = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?array $stopSequences = null,
        public mixed $onChunk = null,
        public mixed $onFinish = null,
    ) {
    }

    /**
     * Create options from an associative array.
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            model: $options['model'] ?? null,
            schema: $options['schema'] ?? null,
            prompt: $options['prompt'] ?? null,
            messages: $options['messages'] ?? null,
            system: $options['system'] ?? null,
            maxTokens: isset($options['maxTokens']) ? (int) $options['maxTokens'] : null,
            temperature: isset($options['temperature']) ? (float) $options['temperature'] : null,
            topP: isset($options['topP']) ? (float) $options['topP'] : null,
            stopSequences: $options['stopSequences'] ?? null,
            onChunk: $options['onChunk'] ?? null,
            onFinish: $options['onFinish'] ?? null,
        );
    }

    /**
     * Convert to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->model !== null) {
            $result['model'] = $this->model;
        }
        if ($this->schema !== null) {
            $result['schema'] = $this->schema;
        }
        if ($this->prompt !== null) {
            $result['prompt'] = $this->prompt;
        }
        if ($this->messages !== null) {
            $result['messages'] = $this->messages;
        }
        if ($this->system !== null) {
            $result['system'] = $this->system;
        }
        if ($this->maxTokens !== null) {
            $result['maxTokens'] = $this->maxTokens;
        }
        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $result['topP'] = $this->topP;
        }
        if ($this->stopSequences !== null) {
            $result['stopSequences'] = $this->stopSequences;
        }
        if ($this->onChunk !== null) {
            $result['onChunk'] = $this->onChunk;
        }
        if ($this->onFinish !== null) {
            $result['onFinish'] = $this->onFinish;
        }

        return $result;
    }
}
