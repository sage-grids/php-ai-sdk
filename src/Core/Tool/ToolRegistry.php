<?php

namespace SageGrids\PhpAiSdk\Core\Tool;

use ReflectionClass;
use RuntimeException;
use SageGrids\PhpAiSdk\Core\Tool\Attributes\Tool as ToolAttribute;

/**
 * Registry for managing tools.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /**
     * Register a tool.
     *
     * @param Tool $tool The tool to register.
     * @return $this
     * @throws RuntimeException If a tool with the same name already exists.
     */
    public function register(Tool $tool): self
    {
        if (isset($this->tools[$tool->name])) {
            throw new RuntimeException("Tool already registered: {$tool->name}");
        }

        $this->tools[$tool->name] = $tool;

        return $this;
    }

    /**
     * Register a tool, replacing any existing tool with the same name.
     *
     * @param Tool $tool The tool to register.
     * @return $this
     */
    public function set(Tool $tool): self
    {
        $this->tools[$tool->name] = $tool;

        return $this;
    }

    /**
     * Register all methods with #[Tool] attribute from an object.
     *
     * @param object $instance The object containing tool methods.
     * @return $this
     */
    public function registerObject(object $instance): self
    {
        $reflection = new ReflectionClass($instance);

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(ToolAttribute::class);

            if (!empty($attributes)) {
                $tool = Tool::fromMethod($instance, $method->getName());
                $this->register($tool);
            }
        }

        return $this;
    }

    /**
     * Get a tool by name.
     *
     * @param string $name The tool name.
     * @return Tool|null The tool, or null if not found.
     */
    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a tool is registered.
     *
     * @param string $name The tool name.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Remove a tool from the registry.
     *
     * @param string $name The tool name.
     * @return $this
     */
    public function remove(string $name): self
    {
        unset($this->tools[$name]);

        return $this;
    }

    /**
     * Get all registered tools.
     *
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Get all tool names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Get the number of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->tools = [];

        return $this;
    }

    /**
     * Convert all tools to provider format.
     *
     * @param string $provider The provider name.
     * @return array<int, array<string, mixed>>
     */
    public function toProviderFormat(string $provider): array
    {
        return array_values(
            array_map(
                fn (Tool $tool) => $tool->toProviderFormat($provider),
                $this->tools
            )
        );
    }

    /**
     * Convert all tools to array format (OpenAI format).
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(
            array_map(
                fn (Tool $tool) => $tool->toArray(),
                $this->tools
            )
        );
    }
}
