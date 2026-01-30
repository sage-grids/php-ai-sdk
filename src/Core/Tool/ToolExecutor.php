<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Tool;

use SageGrids\PhpAiSdk\Exception\ToolSecurityException;
use SageGrids\PhpAiSdk\Result\ToolCall;
use Throwable;

/**
 * Executes tools and handles errors.
 *
 * This executor supports security policies to control which tools can be
 * executed and under what conditions. When a policy is provided, all tool
 * calls are validated against it before execution.
 *
 * @see ToolExecutionPolicy For configuring security controls.
 */
final class ToolExecutor
{
    private ?ToolExecutionPolicy $policy = null;

    /**
     * Create a new executor with an optional security policy.
     *
     * @param ToolExecutionPolicy|null $policy The security policy to enforce.
     */
    public function __construct(?ToolExecutionPolicy $policy = null)
    {
        $this->policy = $policy;
    }

    /**
     * Set the security policy.
     *
     * @param ToolExecutionPolicy|null $policy The security policy to enforce.
     */
    public function setPolicy(?ToolExecutionPolicy $policy): self
    {
        $this->policy = $policy;
        return $this;
    }

    /**
     * Get the current security policy.
     */
    public function getPolicy(): ?ToolExecutionPolicy
    {
        return $this->policy;
    }

    /**
     * Execute a tool with the given tool call.
     *
     * If a security policy is set, the call is validated before execution.
     * Policy violations result in either an exception or an error result,
     * depending on the policy's failOnViolation setting.
     *
     * @param Tool $tool The tool to execute.
     * @param ToolCall $call The tool call containing arguments.
     * @return ToolResult The result of the execution.
     * @throws ToolSecurityException If policy is set to fail on violations.
     */
    public function execute(Tool $tool, ToolCall $call): ToolResult
    {
        // Apply security policy if set
        if ($this->policy !== null) {
            $policyResult = $this->applyPolicy($call);
            if ($policyResult !== null) {
                return $policyResult;
            }
        }

        // Get arguments (possibly sanitized by policy)
        $arguments = $this->policy !== null
            ? $this->policy->sanitizeArguments($call->name, $call->arguments)
            : $call->arguments;

        // Execute with timeout if configured
        $timeout = $this->policy?->getTimeout();
        if ($timeout !== null) {
            return $this->executeWithTimeout($tool, $call, $arguments, $timeout);
        }

        return $this->doExecute($tool, $call, $arguments);
    }

    /**
     * Execute multiple tools from a registry.
     *
     * @param ToolRegistry $registry The tool registry.
     * @param array<ToolCall> $calls The tool calls to execute.
     * @return array<ToolResult> The results of all executions.
     * @throws ToolSecurityException If policy is set to fail on violations.
     */
    public function executeAll(ToolRegistry $registry, array $calls): array
    {
        $results = [];

        foreach ($calls as $call) {
            $tool = $registry->get($call->name);

            if ($tool === null) {
                $results[] = ToolResult::failure(
                    $call->id,
                    new \RuntimeException("Tool not found: {$call->name}")
                );
                continue;
            }

            $results[] = $this->execute($tool, $call);
        }

        return $results;
    }

    /**
     * Apply the security policy to a tool call.
     *
     * @return ToolResult|null A failure result if denied, null if allowed.
     * @throws ToolSecurityException If policy is set to fail on violations.
     */
    private function applyPolicy(ToolCall $call): ?ToolResult
    {
        // This method is only called when policy is not null
        assert($this->policy !== null);

        $error = $this->policy->validate($call);

        if ($error === null) {
            return null;
        }

        // Create appropriate exception
        $exception = $this->createSecurityException($call, $error);

        if ($this->policy->shouldFailOnViolation()) {
            throw $exception;
        }

        return ToolResult::failure($call->id, $exception);
    }

    /**
     * Create a security exception for a policy violation.
     */
    private function createSecurityException(ToolCall $call, string $error): ToolSecurityException
    {
        // This method is only called when policy is not null
        assert($this->policy !== null);

        // Determine the specific reason
        if (str_contains($error, 'explicitly denied')) {
            return ToolSecurityException::explicitlyDenied($call->name, $call->arguments);
        }

        if (str_contains($error, 'not in the allowed')) {
            return ToolSecurityException::notAllowed(
                $call->name,
                $call->arguments,
                $this->policy->getAllowedTools()
            );
        }

        if (str_contains($error, 'confirmation callback')) {
            return ToolSecurityException::confirmationDenied($call->name, $call->arguments);
        }

        // Generic security exception
        return new ToolSecurityException(
            $error,
            $call->name,
            $call->arguments,
            'policy_violation'
        );
    }

    /**
     * Execute the tool without timeout.
     *
     * @param array<string, mixed> $arguments The sanitized arguments.
     */
    private function doExecute(Tool $tool, ToolCall $call, array $arguments): ToolResult
    {
        try {
            $result = $tool->execute($arguments);
            return ToolResult::success($call->id, $result);
        } catch (Throwable $e) {
            return ToolResult::failure($call->id, $e);
        }
    }

    /**
     * Execute the tool with a timeout.
     *
     * Note: PHP does not natively support execution timeouts within a single
     * thread. This implementation uses pcntl_alarm where available, and falls
     * back to no timeout otherwise. For reliable timeouts, consider running
     * tools in separate processes or using async PHP extensions.
     *
     * @param array<string, mixed> $arguments The sanitized arguments.
     */
    private function executeWithTimeout(Tool $tool, ToolCall $call, array $arguments, int $timeout): ToolResult
    {
        // Check if pcntl is available for signal-based timeout
        if (function_exists('pcntl_alarm') && function_exists('pcntl_signal')) {
            return $this->executeWithPcntlTimeout($tool, $call, $arguments, $timeout);
        }

        // Fallback: execute without timeout, add warning in result metadata
        // In production, tools should implement their own timeouts
        return $this->doExecute($tool, $call, $arguments);
    }

    /**
     * Execute with PCNTL-based timeout (Unix only).
     *
     * @param array<string, mixed> $arguments The sanitized arguments.
     */
    private function executeWithPcntlTimeout(Tool $tool, ToolCall $call, array $arguments, int $timeout): ToolResult
    {
        $timedOut = false;

        // Set up signal handler
        pcntl_signal(SIGALRM, function () use (&$timedOut): void {
            $timedOut = true;
        });

        pcntl_alarm($timeout);

        try {
            $result = $tool->execute($arguments);
            pcntl_alarm(0); // Cancel alarm

            if ($timedOut) {
                $exception = ToolSecurityException::timeout($call->name, $arguments, $timeout);
                if ($this->policy?->shouldFailOnViolation()) {
                    throw $exception;
                }
                return ToolResult::failure($call->id, $exception);
            }

            return ToolResult::success($call->id, $result);
        } catch (ToolSecurityException $e) {
            throw $e;
        } catch (Throwable $e) {
            pcntl_alarm(0); // Cancel alarm
            return ToolResult::failure($call->id, $e);
        } finally {
            // Reset to default handler
            pcntl_signal(SIGALRM, SIG_DFL);
        }
    }
}
