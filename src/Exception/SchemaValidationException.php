<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when schema validation fails.
 *
 * This is thrown when data does not conform to a defined JSON schema
 * or when structured output from the AI does not match the expected schema.
 */
final class SchemaValidationException extends ValidationException
{
    /**
     * @param string $message The error message.
     * @param array<ValidationError> $errors The validation errors.
     * @param string|null $schemaName The name of the schema that failed validation.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message,
        array $errors = [],
        public readonly ?string $schemaName = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errors, $code, $previous);
    }

    /**
     * Create a schema validation exception from errors.
     *
     * @param array<ValidationError> $errors The validation errors.
     * @param string|null $schemaName The name of the schema.
     */
    public static function fromSchemaErrors(array $errors, ?string $schemaName = null): self
    {
        $errorMessages = array_map(
            static fn(ValidationError $error): string => (string) $error,
            $errors,
        );

        $message = $schemaName !== null
            ? sprintf('Schema "%s" validation failed with %d error(s): %s', $schemaName, count($errors), implode('; ', $errorMessages))
            : sprintf('Schema validation failed with %d error(s): %s', count($errors), implode('; ', $errorMessages));

        return new self($message, $errors, $schemaName);
    }

    /**
     * Create an exception for invalid JSON output from AI.
     */
    public static function invalidJsonOutput(string $output, ?string $schemaName = null): self
    {
        $error = new ValidationError('$', 'Output is not valid JSON', $output);

        return new self(
            'AI output is not valid JSON',
            [$error],
            $schemaName,
        );
    }

    /**
     * Create an exception for a missing required property.
     */
    public static function missingProperty(string $property, ?string $schemaName = null): self
    {
        $error = ValidationError::required($property);

        return new self(
            sprintf('Required property "%s" is missing', $property),
            [$error],
            $schemaName,
        );
    }

    /**
     * Get structured error details for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'schemaName' => $this->schemaName,
        ]);
    }
}
