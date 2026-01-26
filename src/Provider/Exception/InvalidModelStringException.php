<?php

namespace SageGrids\PhpAiSdk\Provider\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when a model string is invalid.
 */
final class InvalidModelStringException extends InvalidArgumentException
{
    public function __construct(string $modelString)
    {
        parent::__construct("Invalid model string format: '{$modelString}'. Expected format: 'provider/model'.");
    }
}
