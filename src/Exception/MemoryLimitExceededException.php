<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Exception;

/**
 * Exception thrown when the message limit is exceeded during tool roundtrips.
 *
 * This prevents unbounded memory growth in long-running tool conversations.
 */
final class MemoryLimitExceededException extends AIException
{
    public function __construct(
        string $message,
        public readonly int $currentMessageCount,
        public readonly int $maxMessages,
        public readonly int $roundtripCount,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create an exception for message limit exceeded.
     */
    public static function messageLimitExceeded(
        int $currentCount,
        int $maxMessages,
        int $roundtrips,
    ): self {
        return new self(
            sprintf(
                'Message limit exceeded: %d messages (max: %d) after %d tool roundtrips. ' .
                'Consider increasing maxMessages or reducing tool call frequency.',
                $currentCount,
                $maxMessages,
                $roundtrips,
            ),
            $currentCount,
            $maxMessages,
            $roundtrips,
        );
    }

    /**
     * Create an exception when approaching limit as a warning context.
     */
    public static function approachingLimit(
        int $currentCount,
        int $maxMessages,
        int $roundtrips,
    ): self {
        return new self(
            sprintf(
                'Approaching message limit: %d/%d messages (%d%%) after %d roundtrips.',
                $currentCount,
                $maxMessages,
                (int) (($currentCount / $maxMessages) * 100),
                $roundtrips,
            ),
            $currentCount,
            $maxMessages,
            $roundtrips,
        );
    }
}
