<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class NullableSchema extends Schema
{
    public function __construct(
        private readonly Schema $innerSchema
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $inner = $this->innerSchema->toJsonSchema();
        
        // Handle type array if already exists (e.g. ['string', 'integer'])
        if (isset($inner['type'])) {
            if (is_array($inner['type'])) {
                if (!in_array('null', $inner['type'])) {
                    $inner['type'][] = 'null';
                }
            } else {
                $inner['type'] = [$inner['type'], 'null'];
            }
            $schema = $inner;
        } else {
            // Draft-07: represent nullability via anyOf
            $schema = [
                'anyOf' => [
                    $inner,
                    ['type' => 'null'],
                ],
            ];
        }

        if ($this->description) {
            $schema['description'] = $this->description;
        }
        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $schema;
    }

    public function validate(mixed $value): ValidationResult
    {
        if ($value === null) {
            return ValidationResult::valid();
        }

        return $this->innerSchema->validate($value);
    }
}
