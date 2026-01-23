<?php

namespace SageGrids\PhpAiSdk\Core\Message;

final readonly class ToolMessage extends Message
{
    public function __construct(
        public string $toolCallId,
        string $content,
    ) {
        parent::__construct(MessageRole::Tool, $content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
            'tool_call_id' => $this->toolCallId,
        ];
    }
}
