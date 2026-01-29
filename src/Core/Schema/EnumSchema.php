<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Schema;

final class EnumSchema extends Schema
{
    /**
     * @param array<string|int|float|bool> $values
     */
    public function __construct(
        private readonly array $values
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['enum' => $this->values];

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
        if (!in_array($value, $this->values, true)) {
            $allowed = implode(', ', array_map('json_encode', $this->values));
            return ValidationResult::invalid(["Value must be one of: $allowed"]);
        }

        return ValidationResult::valid();
    }
}
