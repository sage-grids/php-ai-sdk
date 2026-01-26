<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Functions;

use Generator;
use SageGrids\PhpAiSdk\Result\TextChunk;

/**
 * Handles streaming text generation.
 */
final class StreamText extends AbstractGenerationFunction
{
    /**
     * Create a new StreamText instance.
     *
     * @param array<string, mixed> $options
     */
    public static function create(array $options): self
    {
        return new self($options);
    }

    /**
     * Execute the streaming text generation.
     *
     * @return Generator<TextChunk>
     */
    public function execute(): Generator
    {
        $generator = $this->provider->streamText(
            messages: $this->messages,
            system: $this->system,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            topP: $this->topP,
            stopSequences: $this->stopSequences,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
        );

        $lastChunk = null;

        foreach ($generator as $chunk) {
            $this->invokeOnChunk($chunk);
            $lastChunk = $chunk;
            yield $chunk;
        }

        // Invoke onFinish with the final chunk
        if ($lastChunk !== null && $lastChunk->isComplete) {
            $this->invokeOnFinish($lastChunk);
        }
    }
}
