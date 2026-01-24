<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

class NumberSchema extends Schema
{
    protected string $type = 'number';
    protected int|float|null $minimum = null;
    protected int|float|null $maximum = null;
    protected int|float|null $multipleOf = null;

    public function minimum(int|float $minimum): static
    {
        $this->minimum = $minimum;
        return $this;
    }

    public function maximum(int|float $maximum): static
    {
        $this->maximum = $maximum;
        return $this;
    }

    public function multipleOf(int|float $multipleOf): static
    {
        $this->multipleOf = $multipleOf;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => $this->type];

        if ($this->description) {
            $schema['description'] = $this->description;
        }
        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }
        if ($this->multipleOf !== null) {
            $schema['multipleOf'] = $this->multipleOf;
        }
        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $schema;
    }

    public function validate(mixed $value): ValidationResult
    {
        if (!is_int($value) && !is_float($value)) {
            return ValidationResult::invalid(['Value must be a number']);
        }

        if ($this->minimum !== null && $value < $this->minimum) {
            return ValidationResult::invalid(["Value must be at least {$this->minimum}"]);
        }

        if ($this->maximum !== null && $value > $this->maximum) {
            return ValidationResult::invalid(["Value must be at most {$this->maximum}"]);
        }

        if ($this->multipleOf !== null) {
            // Use fmod for float safe modulo
            if (fmod((float)$value, (float)$this->multipleOf) != 0) {
                 return ValidationResult::invalid(["Value must be a multiple of {$this->multipleOf}"]);
            }
        }

        return ValidationResult::valid();
    }
}
