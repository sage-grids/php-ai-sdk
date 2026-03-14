<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider;

use SageGrids\PhpAiSdk\Provider\Exception\InvalidModelStringException;
use SageGrids\PhpAiSdk\Provider\Exception\ProviderNotFoundException;

/**
 * Registry for managing AI providers.
 *
 * This class uses the singleton pattern to maintain a global registry of
 * AI providers that can be accessed throughout the application.
 *
 * @warning This class uses static state (singleton) which is NOT thread-safe in
 *          async PHP environments (Swoole, ReactPHP, Amp, Fiber-based concurrency).
 *          The singleton instance and its registered providers are shared across all
 *          coroutines/fibers, which can lead to race conditions when registering,
 *          modifying, or clearing providers during concurrent request handling.
 *
 *          For concurrent request handling in async environments, use {@see \SageGrids\PhpAiSdk\AIContext}
 *          with dependency injection instead. AIContext provides an instance-based
 *          provider registry that is isolated per request/coroutine. Example:
 *
 *          ```php
 *          // Async-safe approach with AIContext
 *          $context = new \SageGrids\PhpAiSdk\AIContext();
 *          $context->registry()->register('openai', new OpenAIProvider($apiKey));
 *
 *          // Each concurrent request gets its own isolated context
 *          $provider = $context->provider('openai');
 *          ```
 *
 * @see \SageGrids\PhpAiSdk\AIContext For thread-safe, instance-based provider management.
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
     * Resolve a provider from a model string.
     *
     * Accepts 'provider/model' format or a bare model name.
     * When a bare model name is given, the first registered provider is used.
     *
     * @throws ProviderNotFoundException
     */
    public function resolve(string $modelString): ProviderInterface
    {
        if (str_contains($modelString, '/')) {
            $parts = explode('/', $modelString, 2);
            if (empty($parts[0]) || empty($parts[1])) {
                throw new InvalidModelStringException($modelString);
            }
            return $this->get($parts[0]);
        }

        // Bare model name — use the first registered provider
        $registered = $this->getRegisteredProviders();
        if (empty($registered)) {
            throw new ProviderNotFoundException('(none)');
        }
        return $this->get($registered[0]);
    }

    /**
     * Parse a model string and return both provider and model name.
     *
     * Accepts 'provider/model' format or a bare model name.
     * When a bare model name is given and the registry has registered providers,
     * the first registered provider is used. Pass $registry to enable this fallback.
     *
     * @return array{provider: string, model: string}
     * @throws InvalidModelStringException
     */
    public static function parseModelString(string $modelString): array
    {
        if (str_contains($modelString, '/')) {
            $parts = explode('/', $modelString, 2);
            if (empty($parts[0]) || empty($parts[1])) {
                throw new InvalidModelStringException($modelString);
            }
            return [
                'provider' => $parts[0],
                'model'    => $parts[1],
            ];
        }

        // Bare model name — use the first registered provider from the singleton registry
        $registry = self::getInstance();
        $registered = $registry->getRegisteredProviders();
        if (empty($registered)) {
            throw new InvalidModelStringException($modelString);
        }

        return [
            'provider' => $registered[0],
            'model'    => $modelString,
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
