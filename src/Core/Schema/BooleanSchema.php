<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class BooleanSchema extends Schema
{
    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => 'boolean'];

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
        if (!is_bool($value)) {
            return ValidationResult::invalid(['Value must be a boolean']);
        }
        return ValidationResult::valid();
    }
}
