<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Represents a tool call made by the AI model.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class ToolCall
{
    /**
     * @param string $id Unique identifier for this tool call.
     * @param string $name The name of the tool to call.
     * @param array<string, mixed> $arguments The arguments to pass to the tool.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
    }
}
