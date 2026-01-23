<?php

namespace SageGrids\PhpAiSdk\Core\Message;

abstract readonly class Message
{
    public function __construct(
        public MessageRole $role,
        public string|array $content,
        public ?float $timestamp = null,
    ) {}

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }
}
