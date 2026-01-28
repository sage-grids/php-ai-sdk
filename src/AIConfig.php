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
 * Static configuration class for global AI settings.
 *
 * This class provides thread-safe access to global AI configuration,
 * allowing you to set default providers, timeouts, and other settings
 * that apply to all AI operations.
 */
final class AIConfig
{
    /** @var ProviderInterface|string|null Default provider or model string */
    private static ProviderInterface|string|null $provider = null;

    /** @var array<string, mixed> Default options for AI operations */
    private static array $defaults = [];

    /** @var int Default timeout in seconds */
    private static int $timeout = 30;

    /** @var int Default max tool roundtrips */
    private static int $maxToolRoundtrips = 5;

    /** @var EventDispatcherInterface Event dispatcher for lifecycle events */
    private static ?EventDispatcherInterface $eventDispatcher = null;

    private function __construct()
    {
    }

    /**
     * Set the default provider.
     *
     * @param ProviderInterface|string $provider A provider instance or model string (e.g., 'openai/gpt-4o').
     */
    public static function setProvider(ProviderInterface|string $provider): void
    {
        self::$provider = $provider;
    }

    /**
     * Get the default provider.
     *
     * @return ProviderInterface|string|null The default provider or model string.
     */
    public static function getProvider(): ProviderInterface|string|null
    {
        return self::$provider;
    }

    /**
     * Set default options that will be merged with operation options.
     *
     * @param array<string, mixed> $defaults Default options.
     */
    public static function setDefaults(array $defaults): void
    {
        self::$defaults = $defaults;
    }

    /**
     * Get the default options.
     *
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        return self::$defaults;
    }

    /**
     * Merge default options with provided options.
     *
     * @param array<string, mixed> $options Provided options.
     * @return array<string, mixed> Merged options (provided options take precedence).
     */
    public static function mergeWithDefaults(array $options): array
    {
        return array_merge(self::$defaults, $options);
    }

    /**
     * Set the default timeout in seconds.
     */
    public static function setTimeout(int $timeout): void
    {
        self::$timeout = $timeout;
    }

    /**
     * Get the default timeout in seconds.
     */
    public static function getTimeout(): int
    {
        return self::$timeout;
    }

    /**
     * Set the default max tool roundtrips.
     */
    public static function setMaxToolRoundtrips(int $maxRoundtrips): void
    {
        self::$maxToolRoundtrips = $maxRoundtrips;
    }

    /**
     * Get the default max tool roundtrips.
     */
    public static function getMaxToolRoundtrips(): int
    {
        return self::$maxToolRoundtrips;
    }

    /**
     * Set the event dispatcher for lifecycle events.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher to use.
     */
    public static function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        self::$eventDispatcher = $dispatcher;
    }

    /**
     * Get the event dispatcher.
     *
     * Returns the configured event dispatcher, or a NullEventDispatcher if
     * none has been configured (providing zero overhead by default).
     *
     * @return EventDispatcherInterface The event dispatcher.
     */
    public static function getEventDispatcher(): EventDispatcherInterface
    {
        return self::$eventDispatcher ?? new NullEventDispatcher();
    }

    /**
     * Auto-configure from environment variables.
     *
     * Looks for common API key environment variables and automatically
     * configures the appropriate provider.
     *
     * Supported environment variables:
     * - OPENAI_API_KEY: Configures OpenAI provider
     * - OPENROUTER_API_KEY: Configures OpenRouter provider for multi-model access
     * - ANTHROPIC_API_KEY: (future) Configures Anthropic provider
     */
    public static function autoConfigureFromEnv(): void
    {
        $registry = ProviderRegistry::getInstance();

        // OpenAI
        $openaiKey = getenv('OPENAI_API_KEY');
        if ($openaiKey !== false && $openaiKey !== '') {
            $provider = new OpenAIProvider($openaiKey);
            $registry->register('openai', $provider);

            // Set as default if no provider is set
            if (self::$provider === null) {
                self::$provider = 'openai/gpt-4o';
            }
        }

        // OpenRouter (multi-model access)
        $openrouterKey = getenv('OPENROUTER_API_KEY');
        if ($openrouterKey !== false && $openrouterKey !== '') {
            $provider = new OpenRouterProvider($openrouterKey);
            $registry->register('openrouter', $provider);

            // Set as default if no provider is set
            if (self::$provider === null) {
                self::$provider = 'openrouter/anthropic/claude-3.5-sonnet';
            }
        }

        // Future: Add more providers here
        // Anthropic, Google, etc.
    }

    /**
     * Reset all configuration to defaults.
     *
     * Useful for testing.
     */
    public static function reset(): void
    {
        self::$provider = null;
        self::$defaults = [];
        self::$timeout = 30;
        self::$maxToolRoundtrips = 5;
        self::$eventDispatcher = null;
    }
}
