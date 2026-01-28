<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Testing;

use SageGrids\PhpAiSdk\Result\EmbeddingData;
use SageGrids\PhpAiSdk\Result\EmbeddingResult;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ImageData;
use SageGrids\PhpAiSdk\Result\ImageResult;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\SpeechResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\TranscriptionResult;
use SageGrids\PhpAiSdk\Result\TranscriptionSegment;
use SageGrids\PhpAiSdk\Result\Usage;

/**
 * Factory helper for creating test responses.
 *
 * Provides convenient static methods for creating response objects commonly
 * used when testing AI SDK functionality with the FakeProvider.
 *
 * @example
 * ```php
 * $fake = new FakeProvider();
 *
 * // Queue a simple text response
 * $fake->addResponse('generateText', FakeResponse::text('Hello, world!'));
 *
 * // Queue a response with tool calls
 * $fake->addResponse('generateText', FakeResponse::toolCalls([
 *     FakeResponse::toolCall('get_weather', ['city' => 'Paris']),
 * ]));
 *
 * // Queue streaming chunks
 * $fake->addStreamResponse('streamText', FakeResponse::streamedText(['Hello', ', ', 'world!']));
 * ```
 */
final class FakeResponse
{
    /**
     * Create a text generation result.
     *
     * @param string $text The generated text.
     * @param Usage|null $usage Token usage statistics.
     * @param FinishReason|null $finishReason The reason generation stopped.
     * @param ToolCall[] $toolCalls Any tool calls in the response.
     * @param array<string, mixed> $rawResponse Raw response data.
     * @return TextResult
     */
    public static function text(
        string $text,
        ?Usage $usage = null,
        ?FinishReason $finishReason = null,
        array $toolCalls = [],
        array $rawResponse = [],
    ): TextResult {
        return new TextResult(
            text: $text,
            finishReason: $finishReason ?? FinishReason::Stop,
            usage: $usage ?? new Usage(10, 5, 15),
            toolCalls: $toolCalls,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create a text result with tool calls.
     *
     * @param ToolCall[] $toolCalls The tool calls to include.
     * @param string $text Optional text content.
     * @param Usage|null $usage Token usage statistics.
     * @return TextResult
     */
    public static function toolCalls(
        array $toolCalls,
        string $text = '',
        ?Usage $usage = null,
    ): TextResult {
        return new TextResult(
            text: $text,
            finishReason: FinishReason::ToolCalls,
            usage: $usage ?? new Usage(10, 5, 15),
            toolCalls: $toolCalls,
        );
    }

    /**
     * Create a single tool call.
     *
     * @param string $name The tool name.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param string|null $id Optional tool call ID.
     * @return ToolCall
     */
    public static function toolCall(
        string $name,
        array $arguments = [],
        ?string $id = null,
    ): ToolCall {
        return new ToolCall(
            id: $id ?? 'call_' . bin2hex(random_bytes(8)),
            name: $name,
            arguments: $arguments,
        );
    }

    /**
     * Create text chunks for streaming responses.
     *
     * @param string[] $textParts The text parts to stream.
     * @param Usage|null $usage Usage for the final chunk.
     * @return TextChunk[]
     */
    public static function streamedText(array $textParts, ?Usage $usage = null): array
    {
        $chunks = [];
        $accumulated = '';

        foreach ($textParts as $i => $part) {
            $accumulated .= $part;
            $isLast = $i === count($textParts) - 1;

            if ($isLast) {
                $chunks[] = TextChunk::final(
                    $accumulated,
                    $part,
                    FinishReason::Stop,
                    $usage ?? new Usage(10, 5, 15),
                );
            } elseif ($i === 0) {
                $chunks[] = TextChunk::first($part);
            } else {
                $chunks[] = TextChunk::continue($accumulated, $part);
            }
        }

        return $chunks;
    }

    /**
     * Create an object generation result.
     *
     * @param mixed $object The generated object (array or typed object).
     * @param string|null $text The raw JSON text (auto-generated if null).
     * @param Usage|null $usage Token usage statistics.
     * @param FinishReason|null $finishReason The reason generation stopped.
     * @return ObjectResult<mixed>
     */
    public static function object(
        mixed $object,
        ?string $text = null,
        ?Usage $usage = null,
        ?FinishReason $finishReason = null,
    ): ObjectResult {
        $jsonText = $text ?? json_encode($object, JSON_THROW_ON_ERROR);

        return new ObjectResult(
            object: $object,
            text: $jsonText,
            finishReason: $finishReason ?? FinishReason::Stop,
            usage: $usage ?? new Usage(10, 5, 15),
        );
    }

    /**
     * Create object chunks for streaming responses.
     *
     * @param mixed $finalObject The final complete object.
     * @param array<mixed> $partialObjects Intermediate partial objects.
     * @param Usage|null $usage Usage for the final chunk.
     * @return ObjectChunk<mixed>[]
     */
    public static function streamedObject(
        mixed $finalObject,
        array $partialObjects = [],
        ?Usage $usage = null,
    ): array {
        $chunks = [];
        $jsonText = json_encode($finalObject, JSON_THROW_ON_ERROR);

        // Add partial chunks
        foreach ($partialObjects as $partial) {
            $partialJson = json_encode($partial, JSON_THROW_ON_ERROR);
            $chunks[] = ObjectChunk::partial($partial, $partialJson);
        }

        // Add final chunk
        $chunks[] = ObjectChunk::final(
            $finalObject,
            $jsonText,
            FinishReason::Stop,
            $usage ?? new Usage(10, 5, 15),
        );

        return $chunks;
    }

    /**
     * Create an embedding result.
     *
     * @param float[]|float[][] $embeddings Single embedding or array of embeddings.
     * @param string $model The model used.
     * @param Usage|null $usage Token usage statistics.
     * @return EmbeddingResult
     */
    public static function embedding(
        array $embeddings,
        string $model = 'fake-embedding-model',
        ?Usage $usage = null,
    ): EmbeddingResult {
        // Normalize to array of embeddings
        if (empty($embeddings) || !is_array($embeddings[0])) {
            $embeddings = [$embeddings];
        }

        /** @var float[][] $normalizedEmbeddings */
        $normalizedEmbeddings = $embeddings;

        $embeddingData = array_map(
            fn (array $embedding, int $index) => new EmbeddingData($index, $embedding),
            $normalizedEmbeddings,
            array_keys($normalizedEmbeddings),
        );

        return new EmbeddingResult(
            embeddings: $embeddingData,
            model: $model,
            usage: $usage ?? new Usage(5, 0, 5),
        );
    }

    /**
     * Create a random embedding vector for testing.
     *
     * @param int $dimensions The number of dimensions.
     * @param int|null $seed Optional seed for reproducible results.
     * @return float[]
     */
    public static function randomEmbedding(int $dimensions = 1536, ?int $seed = null): array
    {
        if ($seed !== null) {
            mt_srand($seed);
        }

        $embedding = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $embedding[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }

        // Normalize to unit vector
        $magnitude = sqrt(array_sum(array_map(fn ($v) => $v * $v, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(fn ($v) => $v / $magnitude, $embedding);
        }

        return $embedding;
    }

    /**
     * Create an image generation result.
     *
     * @param string|null $url The image URL.
     * @param string|null $base64 The base64-encoded image data.
     * @param string|null $revisedPrompt The revised prompt if any.
     * @param int $count Number of images.
     * @param Usage|null $usage Usage statistics.
     * @return ImageResult
     */
    public static function image(
        ?string $url = null,
        ?string $base64 = null,
        ?string $revisedPrompt = null,
        int $count = 1,
        ?Usage $usage = null,
    ): ImageResult {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = new ImageData(
                url: $url ?? "https://example.com/fake-image-{$i}.png",
                base64: $base64,
                revisedPrompt: $revisedPrompt,
            );
        }

        return new ImageResult(
            images: $images,
            usage: $usage,
        );
    }

    /**
     * Create a speech generation result.
     *
     * @param string $audio The audio content (binary data or base64).
     * @param string $format The audio format.
     * @param float|null $duration The duration in seconds.
     * @param Usage|null $usage Usage statistics.
     * @return SpeechResult
     */
    public static function speech(
        string $audio = 'fake-audio-content',
        string $format = 'mp3',
        ?float $duration = null,
        ?Usage $usage = null,
    ): SpeechResult {
        return new SpeechResult(
            audio: $audio,
            format: $format,
            duration: $duration ?? 1.5,
            usage: $usage,
        );
    }

    /**
     * Create a transcription result.
     *
     * @param string $text The transcribed text.
     * @param string|null $language The detected language code.
     * @param float|null $duration The audio duration in seconds.
     * @param TranscriptionSegment[] $segments Detailed segments with timestamps.
     * @param Usage|null $usage Usage statistics.
     * @return TranscriptionResult
     */
    public static function transcription(
        string $text,
        ?string $language = null,
        ?float $duration = null,
        array $segments = [],
        ?Usage $usage = null,
    ): TranscriptionResult {
        return new TranscriptionResult(
            text: $text,
            language: $language ?? 'en',
            duration: $duration ?? 5.0,
            segments: $segments,
            usage: $usage,
        );
    }

    /**
     * Create a transcription segment.
     *
     * @param string $text The segment text.
     * @param float $start Start time in seconds.
     * @param float $end End time in seconds.
     * @param int $id Segment ID.
     * @return TranscriptionSegment
     */
    public static function transcriptionSegment(
        string $text,
        float $start,
        float $end,
        int $id = 0,
    ): TranscriptionSegment {
        return new TranscriptionSegment(
            id: $id,
            start: $start,
            end: $end,
            text: $text,
        );
    }

    /**
     * Create a usage statistics object.
     *
     * @param int $promptTokens Number of prompt tokens.
     * @param int $completionTokens Number of completion tokens.
     * @param int|null $totalTokens Total tokens (auto-calculated if null).
     * @return Usage
     */
    public static function usage(
        int $promptTokens = 10,
        int $completionTokens = 5,
        ?int $totalTokens = null,
    ): Usage {
        return new Usage(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens ?? ($promptTokens + $completionTokens),
        );
    }
}
