<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider;

use SageGrids\PhpAiSdk\Result\SpeechResult;
use SageGrids\PhpAiSdk\Result\TranscriptionResult;

/**
 * Interface for providers that support speech generation and transcription.
 */
interface SpeechProviderInterface extends ProviderInterface
{
    /**
     * Generate speech from text (Text-to-Speech).
     *
     * @param string $text The text to convert to speech.
     * @param string $voice The voice to use for speech generation.
     * @param string|null $model The model to use for generation.
     * @param float $speed Speech speed multiplier (0.25 to 4.0).
     * @param string $responseFormat Audio format ('mp3', 'opus', 'aac', 'flac', 'wav', 'pcm').
     */
    public function generateSpeech(
        string $text,
        string $voice,
        ?string $model = null,
        float $speed = 1.0,
        string $responseFormat = 'mp3',
    ): SpeechResult;

    /**
     * Transcribe audio to text (Speech-to-Text).
     *
     * @param string $audio Path to the audio file or base64 encoded audio.
     * @param string|null $model The model to use for transcription.
     * @param string|null $language The language of the audio (ISO-639-1 code).
     * @param string|null $prompt Optional prompt to guide transcription.
     * @param string $responseFormat Response format ('json', 'text', 'srt', 'verbose_json', 'vtt').
     * @param float|null $temperature Sampling temperature for transcription.
     */
    public function transcribe(
        string $audio,
        ?string $model = null,
        ?string $language = null,
        ?string $prompt = null,
        string $responseFormat = 'json',
        ?float $temperature = null,
    ): TranscriptionResult;

    /**
     * Translate audio to English text.
     *
     * @param string $audio Path to the audio file or base64 encoded audio.
     * @param string|null $model The model to use for translation.
     * @param string|null $prompt Optional prompt to guide translation.
     * @param string $responseFormat Response format ('json', 'text', 'srt', 'verbose_json', 'vtt').
     * @param float|null $temperature Sampling temperature for translation.
     */
    public function translateAudio(
        string $audio,
        ?string $model = null,
        ?string $prompt = null,
        string $responseFormat = 'json',
        ?float $temperature = null,
    ): TranscriptionResult;
}
