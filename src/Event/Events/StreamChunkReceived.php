<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

/**
 * Event dispatched when a streaming chunk is received.
 *
 * This event is dispatched for each chunk during streaming operations,
 * allowing listeners to track progress, log chunks, or perform real-time
 * processing.
 */
final readonly class StreamChunkReceived
{
    /**
     * @param string $provider The provider name (e.g., 'openai', 'anthropic').
     * @param string $model The model identifier (e.g., 'gpt-4o', 'claude-3-opus').
     * @param mixed $chunk The chunk object (TextChunk, ObjectChunk, etc.).
     * @param int $chunkIndex The zero-based index of this chunk in the stream.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public mixed $chunk,
        public int $chunkIndex,
    ) {
    }
}
