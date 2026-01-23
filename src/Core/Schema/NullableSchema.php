<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class NullableSchema extends Schema
{
    public function __construct(
        private readonly Schema $innerSchema
    ) {}

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
        } else {
            // Case for anyOf/oneOf/enum
            $inner['nullable'] = true; 
        }

        if ($this->description) {
            $inner['description'] = $this->description;
        }
        if ($this->defaultValue !== null) {
            $inner['default'] = $this->defaultValue;
        }

        return $inner;
    }

    public function validate(mixed $value): ValidationResult
    {
        if ($value === null) {
            return ValidationResult::valid();
        }

        return $this->innerSchema->validate($value);
    }
}
