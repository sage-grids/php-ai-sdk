<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Message\Formatter;

use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;

/**
 * Message formatter for Google Gemini API.
 *
 * Formats messages in the Gemini format:
 * {
 *   contents: [{role: 'user'|'model', parts: [{text: '...'}]}],
 *   systemInstruction: {parts: [{text: '...'}]}
 * }
 */
final class GeminiMessageFormatter implements MessageFormatterInterface
{
    /**
     * Format messages for the Gemini API.
     *
     * Returns array with 'contents' and optionally 'systemInstruction' keys.
     *
     * @param Message[] $messages Array of Message objects to format.
     * @param string|null $system Optional system prompt/instructions.
     * @return array<string, mixed> Formatted request data with contents and systemInstruction.
     */
    public function format(array $messages, ?string $system): array
    {
        $result = [
            'contents' => $this->formatContents($messages),
        ];

        // System instruction goes in a separate field (not in contents)
        if ($system !== null) {
            $result['systemInstruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        return $result;
    }

    /**
     * Format messages as Gemini contents array.
     *
     * @param Message[] $messages
     * @return array<int, array<string, mixed>>
     */
    private function formatContents(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            // Skip system messages - they're handled via systemInstruction
            if ($message instanceof SystemMessage) {
                continue;
            }

            $role = match (true) {
                $message instanceof UserMessage => 'user',
                $message instanceof AssistantMessage => 'model',
                default => 'user',
            };

            $parts = [];
            /** @var string|array<mixed>|null $content */
            $content = $message->toArray()['content'] ?? '';

            // Handle multimodal content
            if (\is_array($content)) {
                foreach ($content as $contentPart) {
                    if (\is_array($contentPart) && isset($contentPart['type'])) {
                        if ($contentPart['type'] === 'text' && isset($contentPart['text'])) {
                            $parts[] = ['text' => (string) $contentPart['text']];
                        } elseif ($contentPart['type'] === 'image_url') {
                            // Convert image URL to inline data format
                            $imageUrl = (string) ($contentPart['image_url']['url'] ?? '');
                            if (str_starts_with($imageUrl, 'data:')) {
                                // Base64 data URL
                                if (preg_match('/^data:([^;]+);base64,(.+)$/', $imageUrl, $matches)) {
                                    $parts[] = [
                                        'inlineData' => [
                                            'mimeType' => $matches[1],
                                            'data' => $matches[2],
                                        ],
                                    ];
                                }
                            }
                        }
                    }
                }
            } elseif (\is_string($content)) {
                $parts[] = ['text' => $content];
            } else {
                $parts[] = ['text' => ''];
            }

            $formatted[] = [
                'role' => $role,
                'parts' => $parts,
            ];
        }

        return $formatted;
    }
}
