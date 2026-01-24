<?php

namespace SageGrids\PhpAiSdk\Core\Message;

final readonly class ToolMessage extends Message
{
    public function __construct(
        public string $toolCallId,
        public mixed $result,
    ) {
        parent::__construct(MessageRole::Tool, $result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->result,
            'tool_call_id' => $this->toolCallId,
        ];
    }
}
