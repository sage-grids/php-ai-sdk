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
     * {@inheritDoc}
     */
    protected function getOperationName(): string
    {
        return 'streamText';
    }

    /**
     * Execute the streaming text generation.
     *
     * @return Generator<TextChunk>
     */
    public function execute(): Generator
    {
        $startTime = $this->dispatchRequestStarted([
            'messageCount' => count($this->messages),
            'hasTools' => $this->tools !== null,
        ]);

        try {
            $generator = $this->provider->streamText(
                messages: $this->messages,
                model: $this->model,
                system: $this->system,
                maxTokens: $this->maxTokens,
                temperature: $this->temperature,
                topP: $this->topP,
                stopSequences: $this->stopSequences,
                tools: $this->tools,
                toolChoice: $this->toolChoice,
            );

            $lastChunk = null;
            $chunkIndex = 0;

            foreach ($generator as $chunk) {
                $this->dispatchStreamChunkReceived($chunk, $chunkIndex);
                $this->invokeOnChunk($chunk);
                $lastChunk = $chunk;
                $chunkIndex++;
                yield $chunk;
            }

            // Invoke onFinish with the final chunk
            if ($lastChunk !== null && $lastChunk->isComplete) {
                $this->invokeOnFinish($lastChunk);
                $this->dispatchRequestCompleted($lastChunk, $startTime, $lastChunk->usage ?? null);
            }
        } catch (\Throwable $e) {
            $this->dispatchErrorOccurred($e);
            throw $e;
        }
    }
}
