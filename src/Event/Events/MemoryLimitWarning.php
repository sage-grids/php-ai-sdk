<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Event\Events;

use DateTimeImmutable;

/**
 * Event dispatched when message count approaches the configured limit.
 *
 * This warning event is dispatched when the message count reaches 80% of
 * the configured maxMessages limit, allowing listeners to take preventive
 * action before the limit is reached.
 */
final readonly class MemoryLimitWarning
{
    /**
     * @param int $currentMessageCount The current number of messages in the conversation.
     * @param int $maxMessages The configured maximum message limit.
     * @param int $roundtripCount The current tool roundtrip count.
     * @param float $usagePercentage The percentage of limit used (0-100).
     * @param DateTimeImmutable $timestamp When the warning was triggered.
     */
    public function __construct(
        public int $currentMessageCount,
        public int $maxMessages,
        public int $roundtripCount,
        public float $usagePercentage,
        public DateTimeImmutable $timestamp,
    ) {
    }

    /**
     * Create a new MemoryLimitWarning event with the current timestamp.
     */
    public static function create(
        int $currentMessageCount,
        int $maxMessages,
        int $roundtripCount,
    ): self {
        $usagePercentage = ($currentMessageCount / $maxMessages) * 100;

        return new self(
            $currentMessageCount,
            $maxMessages,
            $roundtripCount,
            $usagePercentage,
            new DateTimeImmutable(),
        );
    }

    /**
     * Check if this is a critical warning (above threshold).
     */
    public function isCritical(float $threshold = 90.0): bool
    {
        return $this->usagePercentage >= $threshold;
    }
}
