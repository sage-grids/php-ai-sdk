<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk;

use SageGrids\PhpAiSdk\Event\EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\NullEventDispatcher;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\OpenRouter\OpenRouterProvider;

/**
 * Thread-safe, instance-based AI context for async PHP environments.
 *
 * This class provides an isolated context for AI operations that is safe to use
 * in concurrent/async PHP environments such as Swoole, ReactPHP, Amp, and
 * Fiber-based applications. Unlike the static {@see AIConfig} class, AIContext
 * maintains its own instance-based state, preventing configuration bleeding
 * between concurrent requests.
 *
 * ## Why Use AIContext?
 *
 * In traditional PHP (one process per request), static state in {@see AIConfig}
 * and {@see ProviderRegistry} works fine. However, in async environments where
 * multiple requests are handled concurrently within a single process, static
 * state becomes problematic:
 *
 * - **Race conditions**: Two requests might modify the same static provider
 * - **Configuration bleeding**: Request A's timeout setting affects Request B
 * - **State corruption**: Clearing providers mid-request affects other requests
 *
 * AIContext solves these issues by providing isolated, instance-based state.
 *
 * ## Usage Examples
 *
 * ### Basic Usage with Dependency Injection
 *
 * ```php
 * // Create a context for each request in your DI container or request handler
 * $context = new AIContext();
 *
 * // Configure the context
 * $context->setProvider('openai/gpt-4o');
 * $context->setTimeout(60);
 * $context->setMaxToolRoundtrips(10);
 *
 * // Register providers
 * $context->registry()->register('openai', new OpenAIProvider($apiKey));
 *
 * // Use in your request handler
 * class ChatHandler {
 *     public function __construct(private AIContext $context) {}
 *
 *     public function handle(): Response {
 *         $provider = $this->context->provider('openai');
 *         // ... use provider
 *     }
 * }
 * ```
 *
 * ### Swoole Coroutine Example
 *
 * ```php
 * use Swoole\Http\Server;
 * use Swoole\Http\Request;
 * use Swoole\Http\Response;
 *
 * $server = new Server('0.0.0.0', 9501);
 *
 * $server->on('request', function (Request $req, Response $res) {
 *     // Each coroutine gets its own isolated context
 *     $context = new AIContext();
 *     $context->autoConfigureFromEnv();
 *
 *     // Context is isolated to this request
 *     $context->setTimeout($req->get['timeout'] ?? 30);
 *
 *     // Process request with isolated context
 *     $provider = $context->provider('openai');
 *     // ...
 * });
 *
 * $server->start();
 * ```
 *
 * ### ReactPHP Example
 *
 * ```php
 * use React\Http\HttpServer;
 * use Psr\Http\Message\ServerRequestInterface;
 *
 * $http = new HttpServer(function (ServerRequestInterface $request) {
 *     // Create fresh context per request
 *     $context = new AIContext();
 *     $context->autoConfigureFromEnv();
 *
 *     // Use context for this request only
 *     return handleAIRequest($request, $context);
 * });
 * ```
 *
 * ### Laravel Service Provider Example
 *
 * ```php
 * class AIServiceProvider extends ServiceProvider
 * {
 *     public function register(): void
 *     {
 *         // Bind as a scoped singleton (new instance per request in Octane)
 *         $this->app->scoped(AIContext::class, function ($app) {
 *             $context = new AIContext();
 *             $context->autoConfigureFromEnv();
 *             return $context;
 *         });
 *     }
 * }
 * ```
 *
 * @see AIConfig For static configuration suitable for traditional PHP applications.
 * @see ProviderRegistry For the underlying provider registry implementation.
 */
final class AIContext
{
    /**
     * Instance-based provider registry for this context.
     */
    private ProviderRegistry $registry;

    /**
     * Default provider or model string for this context.
     */
    private ProviderInterface|string|null $provider = null;

    /**
     * Default options for AI operations in this context.
     *
     * @var array<string, mixed>
     */
    private array $defaults = [];

    /**
     * Default timeout in seconds for this context.
     */
    private int $timeout = 30;

    /**
     * Default max tool roundtrips for this context.
     */
    private int $maxToolRoundtrips = 5;

    /**
     * Event dispatcher for lifecycle events in this context.
     */
    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Create a new AI context with isolated state.
     *
     * Each AIContext instance maintains its own provider registry and
     * configuration, making it safe for concurrent use in async environments.
     */
    public function __construct()
    {
        // Create a new ProviderRegistry instance (not the singleton)
        // We use reflection to bypass the private constructor for isolation
        $this->registry = $this->createIsolatedRegistry();
    }

    /**
     * Get the isolated provider registry for this context.
     *
     * This registry is independent of the global singleton registry,
     * allowing isolated provider management per context.
     */
    public function registry(): ProviderRegistry
    {
        return $this->registry;
    }

    /**
     * Get a provider by name from this context's registry.
     *
     * @throws \SageGrids\PhpAiSdk\Provider\Exception\ProviderNotFoundException
     */
    public function provider(string $name): ProviderInterface
    {
        return $this->registry->get($name);
    }

    /**
     * Set the default provider for this context.
     *
     * @param ProviderInterface|string $provider A provider instance or model string (e.g., 'openai/gpt-4o').
     */
    public function setProvider(ProviderInterface|string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Get the default provider for this context.
     *
     * @return ProviderInterface|string|null The default provider or model string.
     */
    public function getProvider(): ProviderInterface|string|null
    {
        return $this->provider;
    }

    /**
     * Set default options that will be merged with operation options.
     *
     * @param array<string, mixed> $defaults Default options.
     */
    public function setDefaults(array $defaults): self
    {
        $this->defaults = $defaults;
        return $this;
    }

    /**
     * Get the default options.
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * Merge default options with provided options.
     *
     * @param array<string, mixed> $options Provided options.
     * @return array<string, mixed> Merged options (provided options take precedence).
     */
    public function mergeWithDefaults(array $options): array
    {
        return [...$this->defaults, ...$options];
    }

    /**
     * Set the default timeout in seconds.
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get the default timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the default max tool roundtrips.
     */
    public function setMaxToolRoundtrips(int $maxRoundtrips): self
    {
        $this->maxToolRoundtrips = $maxRoundtrips;
        return $this;
    }

    /**
     * Get the default max tool roundtrips.
     */
    public function getMaxToolRoundtrips(): int
    {
        return $this->maxToolRoundtrips;
    }

    /**
     * Set the event dispatcher for lifecycle events.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher to use.
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        $this->eventDispatcher = $dispatcher;
        return $this;
    }

    /**
     * Get the event dispatcher.
     *
     * Returns the configured event dispatcher, or a NullEventDispatcher if
     * none has been configured (providing zero overhead by default).
     *
     * @return EventDispatcherInterface The event dispatcher.
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher ?? new NullEventDispatcher();
    }

    /**
     * Auto-configure from environment variables.
     *
     * Looks for common API key environment variables and automatically
     * configures the appropriate provider within this context.
     *
     * Supported environment variables:
     * - OPENAI_API_KEY: Configures OpenAI provider
     * - OPENROUTER_API_KEY: Configures OpenRouter provider for multi-model access
     * - ANTHROPIC_API_KEY: (future) Configures Anthropic provider
     */
    public function autoConfigureFromEnv(): self
    {
        // OpenAI
        $openaiKey = getenv('OPENAI_API_KEY');
        if ($openaiKey !== false && $openaiKey !== '') {
            $provider = new OpenAIProvider($openaiKey);
            $this->registry->register('openai', $provider);

            // Set as default if no provider is set
            if ($this->provider === null) {
                $this->provider = 'openai/gpt-4o';
            }
        }

        // OpenRouter (multi-model access)
        $openrouterKey = getenv('OPENROUTER_API_KEY');
        if ($openrouterKey !== false && $openrouterKey !== '') {
            $provider = new OpenRouterProvider($openrouterKey);
            $this->registry->register('openrouter', $provider);

            // Set as default if no provider is set
            if ($this->provider === null) {
                $this->provider = 'openrouter/anthropic/claude-3.5-sonnet';
            }
        }

        // Future: Add more providers here
        // Anthropic, Google, etc.

        return $this;
    }

    /**
     * Reset this context to defaults.
     *
     * Clears all configuration and providers, returning the context
     * to its initial state.
     */
    public function reset(): self
    {
        $this->provider = null;
        $this->defaults = [];
        $this->timeout = 30;
        $this->maxToolRoundtrips = 5;
        $this->eventDispatcher = null;
        $this->registry->clear();
        return $this;
    }

    /**
     * Create a new isolated ProviderRegistry instance.
     *
     * Uses reflection to bypass the private constructor, allowing
     * creation of independent registry instances for isolation.
     */
    private function createIsolatedRegistry(): ProviderRegistry
    {
        $reflection = new \ReflectionClass(ProviderRegistry::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        // Initialize the providers array via reflection
        $providersProperty = $reflection->getProperty('providers');
        $providersProperty->setValue($instance, []);

        return $instance;
    }
}
