<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

/**
 * Represents a single validation error.
 *
 * This readonly class holds details about a specific validation failure
 * including the path to the invalid value, the error message, and the value itself.
 */
final readonly class ValidationError
{
    /**
     * @param string $path The path to the invalid value (e.g., "messages[0].content").
     * @param string $message The validation error message.
     * @param mixed $value The invalid value that failed validation.
     */
    public function __construct(
        public string $path,
        public string $message,
        public mixed $value = null,
    ) {
    }

    /**
     * Create a validation error for a required field.
     */
    public static function required(string $path): self
    {
        return new self($path, sprintf('The field "%s" is required.', $path));
    }

    /**
     * Create a validation error for an invalid type.
     *
     * @param string $path The path to the invalid value.
     * @param string $expectedType The expected type.
     * @param mixed $value The actual value.
     */
    public static function invalidType(string $path, string $expectedType, mixed $value): self
    {
        $actualType = get_debug_type($value);

        return new self(
            $path,
            sprintf('Expected "%s" to be of type %s, got %s.', $path, $expectedType, $actualType),
            $value,
        );
    }

    /**
     * Create a validation error for a value out of range.
     *
     * @param string $path The path to the invalid value.
     * @param int|float $min The minimum allowed value.
     * @param int|float $max The maximum allowed value.
     * @param int|float $value The actual value.
     */
    public static function outOfRange(string $path, int|float $min, int|float $max, int|float $value): self
    {
        return new self(
            $path,
            sprintf('Value of "%s" must be between %s and %s, got %s.', $path, $min, $max, $value),
            $value,
        );
    }

    /**
     * Create a validation error for a string length violation.
     */
    public static function lengthOutOfRange(string $path, int $minLength, int $maxLength, string $value): self
    {
        $actualLength = mb_strlen($value);

        return new self(
            $path,
            sprintf('Length of "%s" must be between %d and %d characters, got %d.', $path, $minLength, $maxLength, $actualLength),
            $value,
        );
    }

    /**
     * Create a validation error for an invalid enum value.
     *
     * @param string $path The path to the invalid value.
     * @param array<string|int> $allowedValues The allowed values.
     * @param mixed $value The actual value.
     */
    public static function invalidEnumValue(string $path, array $allowedValues, mixed $value): self
    {
        return new self(
            $path,
            sprintf('Value of "%s" must be one of: %s.', $path, implode(', ', $allowedValues)),
            $value,
        );
    }

    /**
     * Create a validation error for a pattern mismatch.
     */
    public static function patternMismatch(string $path, string $pattern, string $value): self
    {
        return new self(
            $path,
            sprintf('Value of "%s" does not match pattern "%s".', $path, $pattern),
            $value,
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{path: string, message: string, value: mixed}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'message' => $this->message,
            'value' => $this->value,
        ];
    }

    /**
     * Get a string representation of the error.
     */
    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->path, $this->message);
    }
}
