<?php

namespace SageGrids\PhpAiSdk\Core\Message;

final readonly class UserMessage extends Message
{
    /**
     * @param string|array<mixed> $content
     */
    public function __construct(string|array $content)
    {
        parent::__construct(MessageRole::User, $content);
    }
}
