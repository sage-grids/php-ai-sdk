<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Message\Formatter;

use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;

/**
 * Message formatter for OpenAI-compatible APIs.
 *
 * Formats messages in the OpenAI chat completion format:
 * [{role: 'system'|'user'|'assistant', content: '...'}]
 */
final class OpenAIMessageFormatter implements MessageFormatterInterface
{
    /**
     * Format messages for the OpenAI API.
     *
     * @param Message[] $messages Array of Message objects to format.
     * @param string|null $system Optional system prompt/instructions.
     * @return array<string, mixed> Formatted messages array.
     */
    public function format(array $messages, ?string $system): array
    {
        $formatted = [];

        // Add system message if provided
        if ($system !== null) {
            $formatted[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        foreach ($messages as $message) {
            // Skip system messages from the messages array if we have a system parameter
            if ($message instanceof SystemMessage && $system !== null) {
                continue;
            }

            $formatted[] = $message->toArray();
        }

        return $formatted;
    }
}
