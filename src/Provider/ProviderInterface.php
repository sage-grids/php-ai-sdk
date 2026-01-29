<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Provider;

/**
 * Base interface for all AI providers.
 */
interface ProviderInterface
{
    /**
     * Get the unique name of the provider.
     */
    public function getName(): string;

    /**
     * Get the capabilities supported by this provider.
     */
    public function getCapabilities(): ProviderCapabilities;

    /**
     * Get the list of available models for this provider.
     *
     * @return string[]
     */
    public function getAvailableModels(): array;
}
