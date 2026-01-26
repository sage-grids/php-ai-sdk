<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a transcription (speech-to-text) or translation request.
 */
final readonly class TranscriptionResult
{
    /**
     * @param string $text The transcribed/translated text.
     * @param string|null $language The detected language (ISO-639-1 code).
     * @param float|null $duration The duration of the audio in seconds.
     * @param TranscriptionSegment[] $segments Detailed segments with timestamps.
     * @param Usage|null $usage Token/usage statistics (if available).
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     */
    public function __construct(
        public string $text,
        public ?string $language = null,
        public ?float $duration = null,
        public array $segments = [],
        public ?Usage $usage = null,
        public array $rawResponse = [],
    ) {
    }

    /**
     * Check if segments with timing information are available.
     */
    public function hasSegments(): bool
    {
        return count($this->segments) > 0;
    }

    /**
     * Get the number of segments.
     */
    public function getSegmentCount(): int
    {
        return count($this->segments);
    }
}
