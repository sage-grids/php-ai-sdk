<?php

namespace SageGrids\PhpAiSdk\Core\Message;

abstract readonly class Message
{
    public function __construct(
        public MessageRole $role,
        public mixed $content,
        public ?float $timestamp = null,
    ) {
        if (!in_array($role, [MessageRole::User, MessageRole::Tool], true) && is_array($content)) {
            throw new \InvalidArgumentException('Only user or tool messages may contain array content.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }
}
