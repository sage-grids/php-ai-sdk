<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class ObjectSchema extends Schema
{
    private bool $additionalProperties = false;

    /**
     * @param array<string, Schema> $properties
     */
    public function __construct(
        private readonly array $properties
    ) {}

    public function additionalProperties(bool $allowed): self
    {
        $this->additionalProperties = $allowed;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => $this->additionalProperties,
        ];

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        foreach ($this->properties as $name => $propertySchema) {
            $schema['properties'][$name] = $propertySchema->toJsonSchema();
            if (!$propertySchema->isOptional()) {
                $schema['required'][] = $name;
            }
        }
        
        if (empty($schema['required'])) {
            unset($schema['required']);
        }

        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $schema;
    }

    public function validate(mixed $value): ValidationResult
    {
        if (!is_array($value) && !is_object($value)) {
            return ValidationResult::invalid(['Value must be an object']);
        }
        
        $valueArray = (array)$value;
        $errors = [];

        // Check required fields and validate types
        foreach ($this->properties as $name => $propertySchema) {
            if (!array_key_exists($name, $valueArray)) {
                if (!$propertySchema->isOptional()) {
                    $errors[] = "Missing required property: $name";
                }
                continue;
            }

            $result = $propertySchema->validate($valueArray[$name]);
            if (!$result->isValid) {
                foreach ($result->errors as $error) {
                    $errors[] = "Property '$name': $error";
                }
            }
        }

        // Check additional properties
        if (!$this->additionalProperties) {
            foreach (array_keys($valueArray) as $key) {
                if (!array_key_exists($key, $this->properties)) {
                    $errors[] = "Unexpected property: $key";
                }
            }
        }

        return $errors ? ValidationResult::invalid($errors) : ValidationResult::valid();
    }
}
