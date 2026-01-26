<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a transcription or translation request.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class TranscriptionResult
{
    /**
     * @param string $text The transcribed/translated text.
     * @param string|null $language The detected language (ISO-639-1 code).
     * @param float|null $duration The duration of the audio in seconds.
     * @param TranscriptionSegment[] $segments Detailed segments with timestamps.
     * @param array<string, mixed> $raw The raw response from the provider.
     */
    public function __construct(
        public string $text,
        public ?string $language = null,
        public ?float $duration = null,
        public array $segments = [],
        public array $raw = [],
    ) {
    }
}
