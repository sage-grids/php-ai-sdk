<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Functions;

use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Result\ObjectResult;

/**
 * Handles synchronous structured object generation.
 */
final class GenerateObject extends AbstractGenerationFunction
{
    private Schema $schema;
    private ?string $schemaName;
    private ?string $schemaDescription;

    /**
     * Create a new GenerateObject instance.
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
     * Execute the object generation.
     *
     * @return ObjectResult<mixed>
     */
    public function execute(): ObjectResult
    {
        // Build system message with schema context
        $effectiveSystem = $this->buildEffectiveSystem();

        $result = $this->provider->generateObject(
            messages: $this->messages,
            schema: $this->schema,
            system: $effectiveSystem,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            topP: $this->topP,
            stopSequences: $this->stopSequences,
        );

        $this->invokeOnFinish($result);

        return $result;
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
