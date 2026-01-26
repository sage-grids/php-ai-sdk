<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * A segment of transcribed text with timing information.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class TranscriptionSegment
{
    /**
     * @param int $id Segment identifier.
     * @param float $start Start time in seconds.
     * @param float $end End time in seconds.
     * @param string $text The transcribed text for this segment.
     */
    public function __construct(
        public int $id,
        public float $start,
        public float $end,
        public string $text,
    ) {
    }
}
