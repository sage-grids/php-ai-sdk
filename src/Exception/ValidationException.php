<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

use Throwable;

/**
 * Exception thrown when validation fails.
 *
 * Contains a collection of validation errors with details about
 * what failed and why.
 *
 * @phpstan-consistent-constructor
 */
class ValidationException extends AIException
{
    /**
     * @param string $message The error message.
     * @param array<ValidationError> $errors The validation errors.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a validation exception from an array of errors.
     *
     * @param array<ValidationError> $errors The validation errors.
     */
    public static function fromErrors(array $errors): static
    {
        $errorMessages = array_map(
            static fn(ValidationError $error): string => (string) $error,
            $errors,
        );

        $message = sprintf(
            'Validation failed with %d error(s): %s',
            count($errors),
            implode('; ', $errorMessages),
        );

        return new static($message, $errors);
    }

    /**
     * Create a validation exception with a single error.
     */
    public static function withError(ValidationError $error): static
    {
        return static::fromErrors([$error]);
    }

    /**
     * Check if there are any validation errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get the first validation error, if any.
     */
    public function getFirstError(): ?ValidationError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get errors for a specific path.
     *
     * @return array<ValidationError>
     */
    public function getErrorsForPath(string $path): array
    {
        return array_values(
            array_filter(
                $this->errors,
                static fn(ValidationError $error): bool => $error->path === $path,
            ),
        );
    }

    /**
     * Get all error paths.
     *
     * @return array<string>
     */
    public function getErrorPaths(): array
    {
        return array_unique(
            array_map(
                static fn(ValidationError $error): string => $error->path,
                $this->errors,
            ),
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
            'errors' => array_map(
                static fn(ValidationError $error): array => $error->toArray(),
                $this->errors,
            ),
            'errorCount' => count($this->errors),
        ]);
    }
}
