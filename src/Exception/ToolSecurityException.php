<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

/**
 * Exception thrown when a tool execution is denied by security policy.
 *
 * This exception is thrown when a tool call is blocked by the
 * ToolExecutionPolicy for security reasons.
 */
final class ToolSecurityException extends AIException
{
    /**
     * @param string $message The error message.
     * @param string $toolName The name of the tool that was denied.
     * @param array<string, mixed> $arguments The arguments that were passed.
     * @param string $reason The reason for denial.
     * @param int $code The exception code.
     */
    public function __construct(
        string $message,
        public readonly string $toolName,
        public readonly array $arguments = [],
        public readonly string $reason = '',
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Create an exception for a tool not in the allowed list.
     *
     * @param string $toolName The denied tool name.
     * @param array<string, mixed> $arguments The arguments.
     * @param string[]|null $allowedTools The list of allowed tools.
     */
    public static function notAllowed(string $toolName, array $arguments = [], ?array $allowedTools = null): self
    {
        $allowed = $allowedTools !== null
            ? implode(', ', $allowedTools)
            : 'none specified';

        return new self(
            sprintf(
                'Tool "%s" is not allowed by security policy. Allowed tools: %s',
                $toolName,
                $allowed
            ),
            $toolName,
            $arguments,
            'not_in_allowed_list',
        );
    }

    /**
     * Create an exception for an explicitly denied tool.
     *
     * @param string $toolName The denied tool name.
     * @param array<string, mixed> $arguments The arguments.
     */
    public static function explicitlyDenied(string $toolName, array $arguments = []): self
    {
        return new self(
            sprintf('Tool "%s" is explicitly denied by security policy.', $toolName),
            $toolName,
            $arguments,
            'explicitly_denied',
        );
    }

    /**
     * Create an exception for a confirmation callback denial.
     *
     * @param string $toolName The denied tool name.
     * @param array<string, mixed> $arguments The arguments.
     */
    public static function confirmationDenied(string $toolName, array $arguments = []): self
    {
        return new self(
            sprintf('Tool "%s" execution was denied by confirmation callback.', $toolName),
            $toolName,
            $arguments,
            'confirmation_denied',
        );
    }

    /**
     * Create an exception for a timeout.
     *
     * @param string $toolName The tool name.
     * @param array<string, mixed> $arguments The arguments.
     * @param int $timeoutSeconds The timeout that was exceeded.
     */
    public static function timeout(string $toolName, array $arguments, int $timeoutSeconds): self
    {
        return new self(
            sprintf('Tool "%s" execution timed out after %d seconds.', $toolName, $timeoutSeconds),
            $toolName,
            $arguments,
            'timeout',
        );
    }

    /**
     * Get structured error details for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'toolName' => $this->toolName,
            'arguments' => $this->arguments,
            'reason' => $this->reason,
        ];
    }
}
