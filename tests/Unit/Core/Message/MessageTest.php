<?php

namespace Tests\Unit\Core\Message;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\MessageCollection;
use SageGrids\PhpAiSdk\Core\Message\MessageRole;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;
use SageGrids\PhpAiSdk\Core\Message\ToolMessage;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;

final class MessageTest extends TestCase
{
    public function testSystemMessage(): void
    {
        $message = new SystemMessage('System prompt');
        $this->assertSame(MessageRole::System, $message->role);
        $this->assertSame('System prompt', $message->content);
        $this->assertSame(['role' => 'system', 'content' => 'System prompt'], $message->toArray());
    }

    public function testUserMessage(): void
    {
        $message = new UserMessage('Hello');
        $this->assertSame(MessageRole::User, $message->role);
        $this->assertSame('Hello', $message->content);
        
        $multiModal = new UserMessage(['type' => 'text', 'text' => 'Hi']);
        $this->assertSame(['type' => 'text', 'text' => 'Hi'], $multiModal->content);
    }

    public function testAssistantMessage(): void
    {
        $message = new AssistantMessage('Response');
        $this->assertSame(MessageRole::Assistant, $message->role);
        $this->assertNull($message->toolCalls);
        
        $toolMessage = new AssistantMessage('', [['id' => '123']]);
        $this->assertSame([['id' => '123']], $toolMessage->toolCalls);
        $this->assertArrayHasKey('tool_calls', $toolMessage->toArray());
    }

    public function testToolMessage(): void
    {
        $message = new ToolMessage('call_123', ['status' => 'ok']);
        $this->assertSame(MessageRole::Tool, $message->role);
        $this->assertSame('call_123', $message->toolCallId);
        $this->assertSame(['status' => 'ok'], $message->result);
        
        $array = $message->toArray();
        $this->assertSame('call_123', $array['tool_call_id']);
        $this->assertSame(['status' => 'ok'], $array['content']);
    }

    public function testMessageCollection(): void
    {
        $collection = new MessageCollection();
        $collection->add(new SystemMessage('Sys'));
        $collection->add(new UserMessage('User'));
        
        $this->assertCount(2, $collection);
        $this->assertCount(2, iterator_to_array($collection));
        
        $array = $collection->toArray();
        $this->assertSame('system', $array[0]['role']);
        $this->assertSame('user', $array[1]['role']);
    }

    public function testMessageCollectionFactories(): void
    {
        $fromMessages = MessageCollection::fromMessages(
            new SystemMessage('Sys'),
            new UserMessage('User'),
        );
        $this->assertCount(2, $fromMessages);

        $fromArray = MessageCollection::fromArray([
            new SystemMessage('Sys'),
            new UserMessage('User'),
        ]);
        $this->assertCount(2, $fromArray);
    }

}
