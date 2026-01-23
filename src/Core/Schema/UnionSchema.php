<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class UnionSchema extends Schema
{
    /**
     * @param array<Schema> $schemas
     */
    public function __construct(
        private readonly array $schemas
    ) {}

    public function toJsonSchema(): array
    {
        $schema = [
            'anyOf' => array_map(fn(Schema $s) => $s->toJsonSchema(), $this->schemas)
        ];

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
        $errors = [];
        foreach ($this->schemas as $schema) {
            $result = $schema->validate($value);
            if ($result->isValid) {
                return ValidationResult::valid();
            }
            $errors = array_merge($errors, $result->errors);
        }

        return ValidationResult::invalid(['Value does not match any of the allowed schemas']);
    }
}
