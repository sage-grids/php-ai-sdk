<?php

namespace SageGrids\PhpAiSdk\Core\Schema;

final class IntegerSchema extends NumberSchema
{
    protected string $type = 'integer';

    public function validate(mixed $value): ValidationResult
    {
        if (!is_int($value)) {
            return ValidationResult::invalid(['Value must be an integer']);
        }
        
        return parent::validate($value);
    }
}
