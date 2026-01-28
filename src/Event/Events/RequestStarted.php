<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

use DateTimeImmutable;

/**
 * Event dispatched when an AI request is about to start.
 *
 * This event is dispatched before calling the provider's API, allowing
 * listeners to log, trace, or modify request parameters.
 */
final readonly class RequestStarted
{
    /**
     * @param string $provider The provider name (e.g., 'openai', 'anthropic').
     * @param string $model The model identifier (e.g., 'gpt-4o', 'claude-3-opus').
     * @param string $operation The operation type ('generateText', 'streamText', 'generateObject', 'streamObject').
     * @param array<string, mixed> $parameters The request parameters.
     * @param DateTimeImmutable $timestamp When the request started.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $operation,
        public array $parameters,
        public DateTimeImmutable $timestamp,
    ) {
    }

    /**
     * Create a new RequestStarted event with the current timestamp.
     *
     * @param string $provider The provider name.
     * @param string $model The model identifier.
     * @param string $operation The operation type.
     * @param array<string, mixed> $parameters The request parameters.
     */
    public static function create(
        string $provider,
        string $model,
        string $operation,
        array $parameters = [],
    ): self {
        return new self(
            $provider,
            $model,
            $operation,
            $parameters,
            new DateTimeImmutable(),
        );
    }
}
