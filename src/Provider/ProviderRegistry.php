<?php

namespace SageGrids\PhpAiSdk\Provider;

use SageGrids\PhpAiSdk\Provider\Exception\InvalidModelStringException;
use SageGrids\PhpAiSdk\Provider\Exception\ProviderNotFoundException;

/**
 * Registry for managing AI providers.
 */
final class ProviderRegistry
{
    private static ?self $instance = null;

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a provider with the registry.
     */
    public function register(string $name, ProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get a provider by name.
     *
     * @throws ProviderNotFoundException
     */
    public function get(string $name): ProviderInterface
    {
        if (!$this->has($name)) {
            throw new ProviderNotFoundException($name);
        }

        return $this->providers[$name];
    }

    /**
     * Check if a provider is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Resolve a provider from a model string (e.g., 'openai/gpt-4o').
     *
     * @throws InvalidModelStringException
     * @throws ProviderNotFoundException
     */
    public function resolve(string $modelString): ProviderInterface
    {
        $parts = explode('/', $modelString, 2);

        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            throw new InvalidModelStringException($modelString);
        }

        return $this->get($parts[0]);
    }

    /**
     * Parse a model string and return both provider and model name.
     *
     * @return array{provider: string, model: string}
     * @throws InvalidModelStringException
     */
    public static function parseModelString(string $modelString): array
    {
        $parts = explode('/', $modelString, 2);

        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            throw new InvalidModelStringException($modelString);
        }

        return [
            'provider' => $parts[0],
            'model' => $parts[1],
        ];
    }

    /**
     * Get all registered provider names.
     *
     * @return string[]
     */
    public function getRegisteredProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Remove a provider from the registry.
     */
    public function unregister(string $name): void
    {
        unset($this->providers[$name]);
    }

    /**
     * Clear all providers from the registry.
     */
    public function clear(): void
    {
        $this->providers = [];
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
