<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

/**
 * Event dispatched when a tool call completes.
 *
 * This event is dispatched after a tool finishes executing, allowing
 * listeners to log results, track timing, or process tool outputs.
 */
final readonly class ToolCallCompleted
{
    /**
     * @param string $toolName The name of the tool that was called.
     * @param array<string, mixed> $arguments The arguments that were passed to the tool.
     * @param mixed $result The result returned by the tool.
     * @param float $duration The execution duration in seconds.
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public mixed $result,
        public float $duration,
    ) {
    }

    /**
     * Create a new ToolCallCompleted event.
     *
     * @param string $toolName The name of the tool.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param mixed $result The tool result.
     * @param float $startTime The execution start time from microtime(true).
     */
    public static function create(
        string $toolName,
        array $arguments,
        mixed $result,
        float $startTime,
    ): self {
        return new self(
            $toolName,
            $arguments,
            $result,
            microtime(true) - $startTime,
        );
    }
}
