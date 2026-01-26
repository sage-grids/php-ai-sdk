<?php

namespace SageGrids\PhpAiSdk\Tool;

use SageGrids\PhpAiSdk\Core\Schema\Schema;

/**
 * Represents a tool that can be called by the AI model.
 *
 * This is a stub class. Full implementation will be added in the tool system task.
 */
class Tool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly Schema $parameters,
        public readonly mixed $handler = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters->toJsonSchema(),
            ],
        ];
    }
}
