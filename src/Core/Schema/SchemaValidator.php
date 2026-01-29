<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Schema;

final class SchemaValidator
{
    public function validate(mixed $value, Schema $schema): ValidationResult
    {
        return $schema->validate($value);
    }
}
