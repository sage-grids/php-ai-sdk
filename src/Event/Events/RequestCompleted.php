<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

use SageGrids\PhpAiSdk\Result\Usage;

/**
 * Event dispatched when an AI request completes successfully.
 *
 * This event is dispatched after receiving a complete response from the
 * provider, allowing listeners to log results, track usage, or perform
 * post-processing.
 */
final readonly class RequestCompleted
{
    /**
     * @param string $provider The provider name (e.g., 'openai', 'anthropic').
     * @param string $model The model identifier (e.g., 'gpt-4o', 'claude-3-opus').
     * @param string $operation The operation type ('generateText', 'streamText', 'generateObject', 'streamObject').
     * @param mixed $result The result object (TextResult, ObjectResult, etc.).
     * @param float $duration The request duration in seconds.
     * @param Usage|null $usage Token usage statistics, if available.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $operation,
        public mixed $result,
        public float $duration,
        public ?Usage $usage,
    ) {
    }

    /**
     * Create a new RequestCompleted event.
     *
     * @param string $provider The provider name.
     * @param string $model The model identifier.
     * @param string $operation The operation type.
     * @param mixed $result The result object.
     * @param float $startTime The request start time from microtime(true).
     * @param Usage|null $usage Token usage statistics.
     */
    public static function create(
        string $provider,
        string $model,
        string $operation,
        mixed $result,
        float $startTime,
        ?Usage $usage = null,
    ): self {
        return new self(
            $provider,
            $model,
            $operation,
            $result,
            microtime(true) - $startTime,
            $usage,
        );
    }
}
