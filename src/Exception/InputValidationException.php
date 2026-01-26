<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when input validation fails.
 *
 * This is thrown when user-provided input to the SDK does not meet
 * the required constraints (e.g., invalid parameters, missing required fields).
 */
final class InputValidationException extends ValidationException
{
    /**
     * @param string $message The error message.
     * @param array<ValidationError> $errors The validation errors.
     * @param string|null $inputName The name of the input that failed validation.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message,
        array $errors = [],
        public readonly ?string $inputName = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errors, $code, $previous);
    }

    /**
     * Create an input validation exception from errors.
     *
     * @param array<ValidationError> $errors The validation errors.
     * @param string|null $inputName The name of the input.
     */
    public static function fromInputErrors(array $errors, ?string $inputName = null): self
    {
        $errorMessages = array_map(
            static fn(ValidationError $error): string => (string) $error,
            $errors,
        );

        $message = $inputName !== null
            ? sprintf('Input "%s" validation failed with %d error(s): %s', $inputName, count($errors), implode('; ', $errorMessages))
            : sprintf('Input validation failed with %d error(s): %s', count($errors), implode('; ', $errorMessages));

        return new self($message, $errors, $inputName);
    }

    /**
     * Create an exception for a required parameter.
     */
    public static function requiredParameter(string $parameter): self
    {
        $error = ValidationError::required($parameter);

        return new self(
            sprintf('Required parameter "%s" is missing', $parameter),
            [$error],
            $parameter,
        );
    }

    /**
     * Create an exception for an invalid parameter type.
     *
     * @param string $parameter The parameter name.
     * @param string $expectedType The expected type.
     * @param mixed $value The actual value.
     */
    public static function invalidParameterType(string $parameter, string $expectedType, mixed $value): self
    {
        $error = ValidationError::invalidType($parameter, $expectedType, $value);

        return new self(
            sprintf('Parameter "%s" has invalid type', $parameter),
            [$error],
            $parameter,
        );
    }

    /**
     * Create an exception for an invalid parameter value.
     */
    public static function invalidParameterValue(string $parameter, string $reason, mixed $value = null): self
    {
        $error = new ValidationError($parameter, $reason, $value);

        return new self(
            sprintf('Parameter "%s" is invalid: %s', $parameter, $reason),
            [$error],
            $parameter,
        );
    }

    /**
     * Create an exception for empty input.
     */
    public static function emptyInput(string $inputName): self
    {
        $error = new ValidationError($inputName, sprintf('The input "%s" cannot be empty.', $inputName));

        return new self(
            sprintf('Input "%s" cannot be empty', $inputName),
            [$error],
            $inputName,
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
            'inputName' => $this->inputName,
        ]);
    }
}
