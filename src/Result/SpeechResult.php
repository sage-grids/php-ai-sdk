<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a speech generation (text-to-speech) request.
 */
final readonly class SpeechResult
{
    /**
     * @param string $audio The audio content (binary data).
     * @param string $format The audio format ('mp3', 'opus', 'aac', 'flac', 'wav', 'pcm').
     * @param float|null $duration The duration of the audio in seconds (if available).
     * @param Usage|null $usage Token/character usage statistics (if available).
     * @param array<string, mixed> $rawResponse The raw response from the provider.
     */
    public function __construct(
        public string $audio,
        public string $format = 'mp3',
        public ?float $duration = null,
        public ?Usage $usage = null,
        public array $rawResponse = [],
    ) {
    }

    /**
     * Get the audio content length in bytes.
     */
    public function getContentLength(): int
    {
        return strlen($this->audio);
    }

    /**
     * Save the audio to a file.
     *
     * @param string $path The file path to save to.
     * @return int|false The number of bytes written, or false on failure.
     */
    public function saveTo(string $path): int|false
    {
        return file_put_contents($path, $this->audio);
    }
}
