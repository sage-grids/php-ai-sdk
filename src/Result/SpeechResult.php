<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a speech generation request.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class SpeechResult
{
    /**
     * @param string $audio The audio content (binary or base64 encoded).
     * @param string $format The audio format ('mp3', 'opus', 'aac', 'flac', 'wav', 'pcm').
     * @param array<string, mixed> $raw The raw response from the provider.
     */
    public function __construct(
        public string $audio,
        public string $format,
        public array $raw = [],
    ) {
    }
}
