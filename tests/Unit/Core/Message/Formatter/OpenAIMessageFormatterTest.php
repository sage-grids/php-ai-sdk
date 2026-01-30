<?php

namespace Tests\Unit\Core\Message\Formatter;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\Formatter\OpenAIMessageFormatter;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;

final class OpenAIMessageFormatterTest extends TestCase
{
    private OpenAIMessageFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new OpenAIMessageFormatter();
    }

    public function testFormatWithSystemPrompt(): void
    {
        $messages = [
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, 'You are a helpful assistant.');

        $this->assertCount(2, $formatted);
        $this->assertSame('system', $formatted[0]['role']);
        $this->assertSame('You are a helpful assistant.', $formatted[0]['content']);
        $this->assertSame('user', $formatted[1]['role']);
        $this->assertSame('Hello!', $formatted[1]['content']);
    }

    public function testFormatWithoutSystemPrompt(): void
    {
        $messages = [
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, null);

        $this->assertCount(1, $formatted);
        $this->assertSame('user', $formatted[0]['role']);
    }

    public function testFormatMultiTurnConversation(): void
    {
        $messages = [
            new UserMessage('What is PHP?'),
            new AssistantMessage('PHP is a server-side scripting language.'),
            new UserMessage('Tell me more.'),
        ];

        $formatted = $this->formatter->format($messages, null);

        $this->assertCount(3, $formatted);
        $this->assertSame('user', $formatted[0]['role']);
        $this->assertSame('assistant', $formatted[1]['role']);
        $this->assertSame('user', $formatted[2]['role']);
    }

    public function testFormatSkipsSystemMessageWhenSystemParameterProvided(): void
    {
        $messages = [
            new SystemMessage('Old system message'),
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, 'New system message');

        $this->assertCount(2, $formatted);
        $this->assertSame('system', $formatted[0]['role']);
        $this->assertSame('New system message', $formatted[0]['content']);
        $this->assertSame('user', $formatted[1]['role']);
    }

    public function testFormatIncludesSystemMessageWhenNoSystemParameter(): void
    {
        $messages = [
            new SystemMessage('System instructions'),
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, null);

        $this->assertCount(2, $formatted);
        $this->assertSame('system', $formatted[0]['role']);
        $this->assertSame('System instructions', $formatted[0]['content']);
    }
}
