<?php

namespace SageGrids\PhpAiSdk\Core\Tool;

use Throwable;

/**
 * Represents the result of executing a tool.
 */
final readonly class ToolResult
{
    /**
     * @param string $toolCallId The ID of the tool call this result is for.
     * @param mixed $result The result of the tool execution.
     * @param Throwable|null $error The error if execution failed.
     */
    public function __construct(
        public string $toolCallId,
        public mixed $result,
        public ?Throwable $error = null,
    ) {
    }

    /**
     * Create a successful result.
     */
    public static function success(string $toolCallId, mixed $result): self
    {
        return new self($toolCallId, $result, null);
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $toolCallId, Throwable $error): self
    {
        return new self($toolCallId, null, $error);
    }

    /**
     * Check if the result is successful.
     */
    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /**
     * Check if the result is a failure.
     */
    public function isFailure(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get the error message if failed.
     */
    public function getErrorMessage(): ?string
    {
        return $this->error?->getMessage();
    }

    /**
     * Convert to array format for provider responses.
     *
     * @return array{tool_call_id: string, content: string}
     */
    public function toArray(): array
    {
        $content = $this->isSuccess()
            ? (is_string($this->result) ? $this->result : json_encode($this->result))
            : "Error: {$this->getErrorMessage()}";

        return [
            'tool_call_id' => $this->toolCallId,
            'content' => $content ?: '',
        ];
    }
}
