<?php

declare(strict_types=1);

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

    public static function fromMessages(Message ...$messages): self
    {
        $collection = new self();
        foreach ($messages as $message) {
            $collection->add($message);
        }
        return $collection;
    }

    /**
     * @param array<Message> $messages
     */
    public static function fromArray(array $messages): self
    {
        $collection = new self();
        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                throw new \InvalidArgumentException('All items must be instances of Message.');
            }
            $collection->add($message);
        }
        return $collection;
    }

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (Message $m) => $m->toArray(), $this->messages);
    }
}
