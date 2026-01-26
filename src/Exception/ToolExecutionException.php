<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when tool execution fails.
 *
 * This exception provides context about which tool failed, what arguments
 * were passed, and the original exception if available.
 */
final class ToolExecutionException extends AIException
{
    /**
     * @param string $message The error message.
     * @param string $toolName The name of the tool that failed.
     * @param array<string, mixed> $arguments The arguments passed to the tool.
     * @param Throwable|null $originalException The original exception that caused the failure.
     * @param int $code The exception code.
     */
    public function __construct(
        string $message,
        public readonly string $toolName,
        public readonly array $arguments = [],
        public readonly ?Throwable $originalException = null,
        int $code = 0,
    ) {
        parent::__construct($message, $code, $originalException);
    }

    /**
     * Create an exception for a tool execution failure.
     *
     * @param string $toolName The name of the tool.
     * @param array<string, mixed> $arguments The arguments passed to the tool.
     * @param Throwable $exception The original exception.
     */
    public static function fromException(string $toolName, array $arguments, Throwable $exception): self
    {
        return new self(
            sprintf('Tool "%s" execution failed: %s', $toolName, $exception->getMessage()),
            $toolName,
            $arguments,
            $exception,
            (int) $exception->getCode(),
        );
    }

    /**
     * Create an exception for a tool not found.
     */
    public static function toolNotFound(string $toolName): self
    {
        return new self(
            sprintf('Tool "%s" is not registered.', $toolName),
            $toolName,
        );
    }

    /**
     * Create an exception for invalid tool arguments.
     *
     * @param string $toolName The name of the tool.
     * @param array<string, mixed> $arguments The invalid arguments.
     * @param string $reason The reason the arguments are invalid.
     */
    public static function invalidArguments(string $toolName, array $arguments, string $reason): self
    {
        return new self(
            sprintf('Invalid arguments for tool "%s": %s', $toolName, $reason),
            $toolName,
            $arguments,
        );
    }

    /**
     * Create an exception for a tool timeout.
     *
     * @param array<string, mixed> $arguments The arguments passed to the tool.
     */
    public static function timeout(string $toolName, array $arguments, int $timeoutSeconds): self
    {
        return new self(
            sprintf('Tool "%s" execution timed out after %d seconds.', $toolName, $timeoutSeconds),
            $toolName,
            $arguments,
        );
    }

    /**
     * Create an exception for a tool returning invalid output.
     *
     * @param string $toolName The name of the tool.
     * @param mixed $output The invalid output.
     * @param string $reason The reason the output is invalid.
     */
    public static function invalidOutput(string $toolName, mixed $output, string $reason): self
    {
        return new self(
            sprintf('Tool "%s" returned invalid output: %s', $toolName, $reason),
            $toolName,
            ['output' => $output],
        );
    }

    /**
     * Create an exception for a tool that is not callable.
     */
    public static function notCallable(string $toolName): self
    {
        return new self(
            sprintf('Tool "%s" is not callable.', $toolName),
            $toolName,
        );
    }

    /**
     * Get structured error details for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = array_merge(parent::toArray(), [
            'toolName' => $this->toolName,
            'arguments' => $this->arguments,
        ]);

        if ($this->originalException !== null) {
            $data['originalException'] = [
                'class' => $this->originalException::class,
                'message' => $this->originalException->getMessage(),
                'code' => $this->originalException->getCode(),
            ];
        }

        return $data;
    }
}
