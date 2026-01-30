<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Tool;

use Closure;
use SageGrids\PhpAiSdk\Result\ToolCall;

/**
 * Security policy for tool execution.
 *
 * This class provides configurable security controls for tool execution,
 * helping to mitigate risks when executing tools based on AI model output.
 *
 * ## Security Features
 *
 * - **Tool Whitelisting**: Only explicitly allowed tools can be executed
 * - **Confirmation Callback**: Require user/system confirmation before execution
 * - **Execution Timeout**: Prevent runaway tool executions
 * - **Argument Sanitization**: Transform/validate arguments before execution
 *
 * ## Usage Example
 *
 * ```php
 * // Create a restrictive policy
 * $policy = ToolExecutionPolicy::create()
 *     ->allowTools(['get_weather', 'search_database'])
 *     ->withTimeout(30)
 *     ->withConfirmation(function (string $toolName, array $args) {
 *         // Log and approve non-destructive operations
 *         logger()->info("Tool call: $toolName", $args);
 *         return !str_starts_with($toolName, 'delete_');
 *     });
 *
 * // Use in generation
 * $result = generateText([
 *     'model' => 'openai/gpt-4o',
 *     'prompt' => 'What is the weather in Paris?',
 *     'tools' => [$weatherTool],
 *     'toolExecutionPolicy' => $policy,
 * ]);
 * ```
 *
 * @see ToolExecutor For the executor that enforces this policy.
 */
final class ToolExecutionPolicy
{
    /**
     * List of allowed tool names. Null means all tools are allowed.
     *
     * @var string[]|null
     */
    private ?array $allowedTools = null;

    /**
     * List of denied tool names. Takes precedence over allowed list.
     *
     * @var string[]
     */
    private array $deniedTools = [];

    /**
     * Confirmation callback. Returns true to allow, false to deny.
     *
     * @var (Closure(string, array<string, mixed>): bool)|null
     */
    private ?Closure $confirmationCallback = null;

    /**
     * Execution timeout in seconds. Null means no timeout.
     */
    private ?int $timeoutSeconds = null;

    /**
     * Argument sanitizer callback. Returns sanitized arguments.
     *
     * @var (Closure(string, array<string, mixed>): array<string, mixed>)|null
     */
    private ?Closure $argumentSanitizer = null;

    /**
     * Whether to fail on policy violations or return error results.
     */
    private bool $failOnViolation = false;

    /**
     * Create a new execution policy with default settings.
     *
     * Default policy allows all tools with no restrictions.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create a restrictive policy that denies all tools by default.
     *
     * Tools must be explicitly allowed using allowTools().
     */
    public static function restrictive(): self
    {
        $policy = new self();
        $policy->allowedTools = [];
        return $policy;
    }

    /**
     * Set the list of allowed tool names.
     *
     * When set, only tools in this list can be executed.
     * Pass null to allow all tools (default behavior).
     *
     * @param string[]|null $toolNames Tool names to allow, or null for all.
     */
    public function allowTools(?array $toolNames): self
    {
        $clone = clone $this;
        $clone->allowedTools = $toolNames;
        return $clone;
    }

    /**
     * Add tools to the allowed list.
     *
     * If no allowlist exists, creates one with only these tools.
     *
     * @param string[] $toolNames Tool names to add.
     */
    public function addAllowedTools(array $toolNames): self
    {
        $clone = clone $this;
        if ($clone->allowedTools === null) {
            $clone->allowedTools = $toolNames;
        } else {
            $clone->allowedTools = array_unique([...$clone->allowedTools, ...$toolNames]);
        }
        return $clone;
    }

    /**
     * Set the list of denied tool names.
     *
     * Denied tools take precedence over allowed tools.
     *
     * @param string[] $toolNames Tool names to deny.
     */
    public function denyTools(array $toolNames): self
    {
        $clone = clone $this;
        $clone->deniedTools = $toolNames;
        return $clone;
    }

    /**
     * Add tools to the denied list.
     *
     * @param string[] $toolNames Tool names to add to deny list.
     */
    public function addDeniedTools(array $toolNames): self
    {
        $clone = clone $this;
        $clone->deniedTools = array_unique([...$clone->deniedTools, ...$toolNames]);
        return $clone;
    }

    /**
     * Set a confirmation callback.
     *
     * The callback receives the tool name and arguments, and should return
     * true to allow execution or false to deny. This is useful for:
     *
     * - Logging all tool calls
     * - Requiring human approval for sensitive operations
     * - Implementing rate limiting
     * - Adding additional business logic checks
     *
     * @param callable(string, array<string, mixed>): bool $callback
     */
    public function withConfirmation(callable $callback): self
    {
        $clone = clone $this;
        $clone->confirmationCallback = Closure::fromCallable($callback);
        return $clone;
    }

    /**
     * Set the execution timeout in seconds.
     *
     * When set, tool execution will be interrupted if it exceeds this timeout.
     * Note: Timeout enforcement depends on the execution environment and may
     * not work with all tool implementations.
     *
     * @param int|null $seconds Timeout in seconds, or null for no timeout.
     */
    public function withTimeout(?int $seconds): self
    {
        $clone = clone $this;
        $clone->timeoutSeconds = $seconds;
        return $clone;
    }

    /**
     * Set an argument sanitizer callback.
     *
     * The callback receives the tool name and arguments, and should return
     * sanitized arguments. This is useful for:
     *
     * - Removing sensitive data from arguments
     * - Validating argument values
     * - Transforming arguments to safe formats
     * - Adding default values
     *
     * @param callable(string, array<string, mixed>): array<string, mixed> $sanitizer
     */
    public function withArgumentSanitizer(callable $sanitizer): self
    {
        $clone = clone $this;
        $clone->argumentSanitizer = Closure::fromCallable($sanitizer);
        return $clone;
    }

    /**
     * Set whether to throw exceptions on policy violations.
     *
     * When true, policy violations will throw ToolSecurityException.
     * When false (default), violations return ToolResult with error.
     *
     * @param bool $fail Whether to throw on violations.
     */
    public function failOnViolation(bool $fail = true): self
    {
        $clone = clone $this;
        $clone->failOnViolation = $fail;
        return $clone;
    }

    /**
     * Check if a tool is allowed by this policy.
     *
     * @param string $toolName The tool name to check.
     * @return bool True if the tool is allowed.
     */
    public function isToolAllowed(string $toolName): bool
    {
        // Check deny list first (takes precedence)
        if (in_array($toolName, $this->deniedTools, true)) {
            return false;
        }

        // If allow list exists, tool must be in it
        if ($this->allowedTools !== null) {
            return in_array($toolName, $this->allowedTools, true);
        }

        // No restrictions, allow
        return true;
    }

    /**
     * Request confirmation for a tool execution.
     *
     * @param string $toolName The tool name.
     * @param array<string, mixed> $arguments The tool arguments.
     * @return bool True if confirmed, false if denied.
     */
    public function confirmExecution(string $toolName, array $arguments): bool
    {
        if ($this->confirmationCallback === null) {
            return true;
        }

        return ($this->confirmationCallback)($toolName, $arguments);
    }

    /**
     * Sanitize arguments before execution.
     *
     * @param string $toolName The tool name.
     * @param array<string, mixed> $arguments The original arguments.
     * @return array<string, mixed> The sanitized arguments.
     */
    public function sanitizeArguments(string $toolName, array $arguments): array
    {
        if ($this->argumentSanitizer === null) {
            return $arguments;
        }

        return ($this->argumentSanitizer)($toolName, $arguments);
    }

    /**
     * Get the timeout in seconds.
     *
     * @return int|null The timeout, or null for no timeout.
     */
    public function getTimeout(): ?int
    {
        return $this->timeoutSeconds;
    }

    /**
     * Check if policy violations should throw exceptions.
     */
    public function shouldFailOnViolation(): bool
    {
        return $this->failOnViolation;
    }

    /**
     * Get the list of allowed tools.
     *
     * @return string[]|null The allowed tools, or null if all are allowed.
     */
    public function getAllowedTools(): ?array
    {
        return $this->allowedTools;
    }

    /**
     * Get the list of denied tools.
     *
     * @return string[]
     */
    public function getDeniedTools(): array
    {
        return $this->deniedTools;
    }

    /**
     * Check if the policy has any restrictions.
     */
    public function hasRestrictions(): bool
    {
        return $this->allowedTools !== null
            || !empty($this->deniedTools)
            || $this->confirmationCallback !== null
            || $this->timeoutSeconds !== null
            || $this->argumentSanitizer !== null;
    }

    /**
     * Validate a tool call against this policy.
     *
     * Returns null if allowed, or an error message if denied.
     *
     * @param ToolCall $call The tool call to validate.
     * @return string|null Error message if denied, null if allowed.
     */
    public function validate(ToolCall $call): ?string
    {
        if (!$this->isToolAllowed($call->name)) {
            if (in_array($call->name, $this->deniedTools, true)) {
                return "Tool '{$call->name}' is explicitly denied by security policy.";
            }
            return "Tool '{$call->name}' is not in the allowed tools list.";
        }

        $sanitizedArgs = $this->sanitizeArguments($call->name, $call->arguments);

        if (!$this->confirmExecution($call->name, $sanitizedArgs)) {
            return "Tool '{$call->name}' execution was denied by confirmation callback.";
        }

        return null;
    }
}
