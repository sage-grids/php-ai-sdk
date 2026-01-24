<?php

namespace SageGrids\PhpAiSdk\Core\Message;

final readonly class AssistantMessage extends Message
{
    /**
     * @param array<mixed>|null $toolCalls
     */
    public function __construct(string $content, public ?array $toolCalls = null)
    {
        parent::__construct(MessageRole::Assistant, $content);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        if ($this->toolCalls) {
            $data['tool_calls'] = $this->toolCalls;
        }
        return $data;
    }
}
