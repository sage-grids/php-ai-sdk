<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Represents a tool call made by the AI model.
 */
final readonly class ToolCall
{
    /**
     * @param string $id Unique identifier for this tool call.
     * @param string $name The name of the tool to call.
     * @param array<string, mixed> $arguments The arguments to pass to the tool.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
    }

    /**
     * Create from a provider's tool call response.
     *
     * @param array<string, mixed> $data Provider response data.
     */
    public static function fromArray(array $data): self
    {
        $arguments = $data['arguments'] ?? $data['input'] ?? [];
        if (is_string($arguments)) {
            $decoded = json_decode($arguments, true);
            $arguments = is_array($decoded) ? $decoded : [];
        }

        /** @var array<string, mixed> $function */
        $function = $data['function'] ?? [];

        /** @var string|null $id */
        $id = $data['id'] ?? '';
        /** @var string|null $nameFromData */
        $nameFromData = $data['name'] ?? null;
        /** @var string|null $nameFromFunction */
        $nameFromFunction = $function['name'] ?? '';

        return new self(
            id: (string) $id,
            name: $nameFromData !== null ? (string) $nameFromData : (string) $nameFromFunction,
            arguments: is_array($arguments) ? $arguments : [],
        );
    }

    /**
     * Convert to array format for provider requests.
     *
     * @return array{id: string, type: string, function: array{name: string, arguments: string}}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments) ?: '{}',
            ],
        ];
    }
}
