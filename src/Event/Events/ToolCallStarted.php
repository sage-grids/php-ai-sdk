<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

use DateTimeImmutable;

/**
 * Event dispatched when a tool call is about to be executed.
 *
 * This event is dispatched before executing a tool that was requested
 * by the AI model, allowing listeners to log, validate, or intercept
 * tool calls.
 */
final readonly class ToolCallStarted
{
    /**
     * @param string $toolName The name of the tool being called.
     * @param array<string, mixed> $arguments The arguments passed to the tool.
     * @param DateTimeImmutable $timestamp When the tool call started.
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public DateTimeImmutable $timestamp,
    ) {
    }

    /**
     * Create a new ToolCallStarted event with the current timestamp.
     *
     * @param string $toolName The name of the tool.
     * @param array<string, mixed> $arguments The tool arguments.
     */
    public static function create(
        string $toolName,
        array $arguments = [],
    ): self {
        return new self(
            $toolName,
            $arguments,
            new DateTimeImmutable(),
        );
    }
}
