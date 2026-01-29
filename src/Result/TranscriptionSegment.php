<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Result;

/**
 * A segment of transcribed text with timing information.
 */
final readonly class TranscriptionSegment
{
    /**
     * @param int $id Segment identifier.
     * @param float $start Start time in seconds.
     * @param float $end End time in seconds.
     * @param string $text The transcribed text for this segment.
     */
    public function __construct(
        public int $id,
        public float $start,
        public float $end,
        public string $text,
    ) {
    }

    /**
     * Get the duration of this segment in seconds.
     */
    public function getDuration(): float
    {
        return $this->end - $this->start;
    }

    /**
     * Create from a provider's segment response.
     *
     * @param array<string, mixed> $data Provider response data.
     */
    public static function fromArray(array $data): self
    {
        /** @var int|string|null $id */
        $id = $data['id'] ?? 0;
        /** @var int|float|string|null $start */
        $start = $data['start'] ?? 0.0;
        /** @var int|float|string|null $end */
        $end = $data['end'] ?? 0.0;
        /** @var string|null $text */
        $text = $data['text'] ?? '';

        return new self(
            id: (int) $id,
            start: (float) $start,
            end: (float) $end,
            text: (string) $text,
        );
    }
}
