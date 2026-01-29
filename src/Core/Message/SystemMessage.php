<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Message;

final readonly class SystemMessage extends Message
{
    public function __construct(string $content)
    {
        parent::__construct(MessageRole::System, $content);
    }
}
