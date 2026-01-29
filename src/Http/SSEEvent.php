<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http;

class SSEEvent
{
    public function __construct(
        public readonly ?string $event = null,
        public readonly mixed $data = null,
        public readonly ?string $id = null,
        public readonly ?int $retry = null,
    ) {
    }
}
