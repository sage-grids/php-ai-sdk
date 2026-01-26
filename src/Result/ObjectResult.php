<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Result of a structured object generation request.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class ObjectResult
{
    /**
     * @param mixed $object The generated object (parsed from JSON).
     * @param string $rawJson The raw JSON string.
     * @param string|null $finishReason The reason generation stopped.
     * @param Usage|null $usage Token usage statistics.
     * @param array<string, mixed> $raw The raw response from the provider.
     */
    public function __construct(
        public mixed $object,
        public string $rawJson,
        public ?string $finishReason = null,
        public ?Usage $usage = null,
        public array $raw = [],
    ) {
    }
}
