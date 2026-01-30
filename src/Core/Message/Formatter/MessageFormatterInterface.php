<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Message\Formatter;

use SageGrids\PhpAiSdk\Core\Message\Message;

/**
 * Interface for provider-specific message formatters.
 *
 * Implementations handle the conversion of SDK Message objects
 * to the format required by each AI provider's API.
 */
interface MessageFormatterInterface
{
    /**
     * Format messages for the provider's API.
     *
     * @param Message[] $messages Array of Message objects to format.
     * @param string|null $system Optional system prompt/instructions.
     * @return array<string, mixed> Provider-specific formatted request data.
     */
    public function format(array $messages, ?string $system): array;
}
