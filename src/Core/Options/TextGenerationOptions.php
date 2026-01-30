<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Options;

use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;

/**
 * Options for text generation requests.
 *
 * This DTO provides type-safe options for text generation operations.
 * It maintains backwards compatibility with array-based options.
 */
final readonly class TextGenerationOptions
{
    /**
     * @param string|ProviderInterface|null $model The model identifier (e.g., 'openai/gpt-4o') or provider instance.
     * @param string|null $prompt The user prompt text.
     * @param Message[]|null $messages Array of Message objects for multi-turn conversations.
     * @param string|null $system The system prompt/instructions.
     * @param int|null $maxTokens Maximum number of tokens to generate.
     * @param float|null $temperature Sampling temperature (0.0 to 2.0).
     * @param float|null $topP Top-p (nucleus) sampling parameter.
     * @param string[]|null $stopSequences Array of sequences that stop generation.
     * @param Tool[]|null $tools Array of Tool objects for function calling.
     * @param string|Tool|null $toolChoice Tool choice mode: 'auto', 'none', 'required', or specific Tool.
     * @param callable|null $onChunk Callback for streaming chunks.
     * @param callable|null $onFinish Callback when generation completes.
     * @param int|null $maxToolRoundtrips Maximum number of tool calling roundtrips.
     */
    public function __construct(
        public string|ProviderInterface|null $model = null,
        public ?string $prompt = null,
        public ?array $messages = null,
        public ?string $system = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?array $stopSequences = null,
        public ?array $tools = null,
        public string|Tool|null $toolChoice = null,
        public mixed $onChunk = null,
        public mixed $onFinish = null,
        public ?int $maxToolRoundtrips = null,
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
            prompt: $options['prompt'] ?? null,
            messages: $options['messages'] ?? null,
            system: $options['system'] ?? null,
            maxTokens: isset($options['maxTokens']) ? (int) $options['maxTokens'] : null,
            temperature: isset($options['temperature']) ? (float) $options['temperature'] : null,
            topP: isset($options['topP']) ? (float) $options['topP'] : null,
            stopSequences: $options['stopSequences'] ?? null,
            tools: $options['tools'] ?? null,
            toolChoice: $options['toolChoice'] ?? null,
            onChunk: $options['onChunk'] ?? null,
            onFinish: $options['onFinish'] ?? null,
            maxToolRoundtrips: isset($options['maxToolRoundtrips']) ? (int) $options['maxToolRoundtrips'] : null,
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
        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $result['toolChoice'] = $this->toolChoice;
        }
        if ($this->onChunk !== null) {
            $result['onChunk'] = $this->onChunk;
        }
        if ($this->onFinish !== null) {
            $result['onFinish'] = $this->onFinish;
        }
        if ($this->maxToolRoundtrips !== null) {
            $result['maxToolRoundtrips'] = $this->maxToolRoundtrips;
        }

        return $result;
    }
}
