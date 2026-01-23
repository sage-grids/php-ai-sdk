<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class StringSchema extends Schema
{
    private ?string $format = null;
    private ?int $minLength = null;
    private ?int $maxLength = null;
    private ?string $pattern = null;

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function minLength(int $minLength): self
    {
        $this->minLength = $minLength;
        return $this;
    }

    public function maxLength(int $maxLength): self
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function pattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function toJsonSchema(): array
    {
        $schema = ['type' => 'string'];

        if ($this->description) {
            $schema['description'] = $this->description;
        }
        if ($this->format) {
            $schema['format'] = $this->format;
        }
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }
        if ($this->pattern) {
            $schema['pattern'] = $this->pattern;
        }
        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $schema;
    }

    public function validate(mixed $value): ValidationResult
    {
        if (!is_string($value)) {
            return ValidationResult::invalid(['Value must be a string']);
        }

        if ($this->minLength !== null && mb_strlen($value) < $this->minLength) {
            return ValidationResult::invalid(["String length must be at least {$this->minLength}"]);
        }

        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            return ValidationResult::invalid(["String length must be at most {$this->maxLength}"]);
        }

        if ($this->pattern !== null && !preg_match($this->pattern, $value)) {
            return ValidationResult::invalid(["String does not match pattern {$this->pattern}"]);
        }

        return ValidationResult::valid();
    }
}
