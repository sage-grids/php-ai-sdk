<?php

namespace SageGrids\PhpAiSdk\Core\Message;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Message>
 */
final class MessageCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<Message>
     */
    private array $messages = [];

    public function add(Message $message): self
    {
        $this->messages[] = $message;
        return $this;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function toArray(): array
    {
        return array_map(fn (Message $m) => $m->toArray(), $this->messages);
    }
}
