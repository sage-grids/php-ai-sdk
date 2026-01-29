<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * Reason why the AI model stopped generating content.
 */
enum FinishReason: string
{
    /** Model reached a natural stopping point. */
    case Stop = 'stop';

    /** Maximum token limit was reached. */
    case Length = 'length';

    /** Model requested tool/function calls. */
    case ToolCalls = 'tool_calls';

    /** Content was filtered due to safety settings. */
    case ContentFilter = 'content_filter';

    /**
     * Create from a provider's finish reason string.
     *
     * Maps various provider-specific values to our standard enum.
     */
    public static function fromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            'stop', 'end_turn', 'complete' => self::Stop,
            'length', 'max_tokens' => self::Length,
            'tool_calls', 'tool_use', 'function_call' => self::ToolCalls,
            'content_filter', 'safety' => self::ContentFilter,
            default => null,
        };
    }
}
