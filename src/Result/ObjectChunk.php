<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * A chunk of structured object data from a streaming response.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class ObjectChunk
{
    /**
     * @param string $partialJson The partial JSON string received so far.
     * @param mixed $partialObject The partially parsed object (may be incomplete).
     * @param bool $isFirst Whether this is the first chunk.
     * @param bool $isFinal Whether this is the final chunk.
     * @param string|null $finishReason The reason generation stopped (only set on final chunk).
     * @param Usage|null $usage Token usage statistics (only set on final chunk).
     */
    public function __construct(
        public string $partialJson,
        public mixed $partialObject = null,
        public bool $isFirst = false,
        public bool $isFinal = false,
        public ?string $finishReason = null,
        public ?Usage $usage = null,
    ) {
    }
}
