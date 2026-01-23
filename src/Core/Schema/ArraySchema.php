<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class ArraySchema extends Schema
{
    private ?int $minItems = null;
    private ?int $maxItems = null;

    public function __construct(
        private readonly Schema $items
    ) {}

    public function minItems(int $minItems): self
    {
        $this->minItems = $minItems;
        return $this;
    }

    public function maxItems(int $maxItems): self
    {
        $this->maxItems = $maxItems;
        return $this;
    }

    public function toJsonSchema(): array
    {
        $schema = [
            'type' => 'array',
            'items' => $this->items->toJsonSchema(),
        ];

        if ($this->description) {
            $schema['description'] = $this->description;
        }
        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }
        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $schema;
    }

    public function validate(mixed $value): ValidationResult
    {
        if (!is_array($value) || !array_is_list($value) && !empty($value)) {
            // PHP arrays are associative by default, check if it's a list
            // Empty array is considered valid list
            return ValidationResult::invalid(['Value must be an array (list)']);
        }

        if ($this->minItems !== null && count($value) < $this->minItems) {
            return ValidationResult::invalid(["Array must contain at least {$this->minItems} items"]);
        }

        if ($this->maxItems !== null && count($value) > $this->maxItems) {
            return ValidationResult::invalid(["Array must contain at most {$this->maxItems} items"]);
        }

        foreach ($value as $index => $item) {
            $result = $this->items->validate($item);
            if (!$result->isValid) {
                $errors = array_map(fn($e) => "Item at index $index: $e", $result->errors);
                return ValidationResult::invalid($errors);
            }
        }

        return ValidationResult::valid();
    }
}
