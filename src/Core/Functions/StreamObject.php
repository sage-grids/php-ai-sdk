<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Functions;

use Generator;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Result\ObjectChunk;

/**
 * Handles streaming structured object generation.
 */
final class StreamObject extends AbstractGenerationFunction
{
    private Schema $schema;
    private ?string $schemaName;
    private ?string $schemaDescription;

    /**
     * Create a new StreamObject instance.
     *
     * @param array<string, mixed> $options
     */
    public static function create(array $options): self
    {
        return new self($options);
    }

    /**
     * @inheritDoc
     */
    protected function parseOptions(): void
    {
        parent::parseOptions();

        // Parse schema (required for object generation)
        $this->schema = $this->parseSchema();
        $this->schemaName = $this->options['schemaName'] ?? null;
        $this->schemaDescription = $this->options['schemaDescription'] ?? null;
    }

    /**
     * Execute the streaming object generation.
     *
     * @return Generator<ObjectChunk<mixed>>
     */
    public function execute(): Generator
    {
        // Build system message with schema context
        $effectiveSystem = $this->buildEffectiveSystem();

        $generator = $this->provider->streamObject(
            messages: $this->messages,
            schema: $this->schema,
            system: $effectiveSystem,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            topP: $this->topP,
            stopSequences: $this->stopSequences,
        );

        $lastChunk = null;

        foreach ($generator as $chunk) {
            $this->invokeOnChunk($chunk);
            $lastChunk = $chunk;
            yield $chunk;
        }

        // Invoke onFinish with the final chunk
        if ($lastChunk !== null && $lastChunk->isComplete) {
            $this->invokeOnFinish($lastChunk);
        }
    }

    /**
     * Build effective system message including schema context.
     */
    private function buildEffectiveSystem(): ?string
    {
        $parts = [];

        if ($this->system !== null) {
            $parts[] = $this->system;
        }

        // Add schema context if name/description provided
        if ($this->schemaName !== null || $this->schemaDescription !== null) {
            $schemaParts = [];
            if ($this->schemaName !== null) {
                $schemaParts[] = "Schema name: {$this->schemaName}";
            }
            if ($this->schemaDescription !== null) {
                $schemaParts[] = "Schema description: {$this->schemaDescription}";
            }
            $parts[] = implode("\n", $schemaParts);
        }

        return empty($parts) ? null : implode("\n\n", $parts);
    }
}
