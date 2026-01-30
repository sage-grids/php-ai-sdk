<?php

namespace Tests\Unit\Core\Message\Formatter;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\Formatter\GeminiMessageFormatter;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;

final class GeminiMessageFormatterTest extends TestCase
{
    private GeminiMessageFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new GeminiMessageFormatter();
    }

    public function testFormatWithSystemPrompt(): void
    {
        $messages = [
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, 'You are a helpful assistant.');

        $this->assertArrayHasKey('contents', $formatted);
        $this->assertArrayHasKey('systemInstruction', $formatted);
        $this->assertCount(1, $formatted['contents']);
        $this->assertSame('user', $formatted['contents'][0]['role']);
        $this->assertSame([['text' => 'Hello!']], $formatted['contents'][0]['parts']);
        $this->assertSame([['text' => 'You are a helpful assistant.']], $formatted['systemInstruction']['parts']);
    }

    public function testFormatWithoutSystemPrompt(): void
    {
        $messages = [
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, null);

        $this->assertArrayHasKey('contents', $formatted);
        $this->assertArrayNotHasKey('systemInstruction', $formatted);
        $this->assertCount(1, $formatted['contents']);
    }

    public function testFormatMultiTurnConversation(): void
    {
        $messages = [
            new UserMessage('What is PHP?'),
            new AssistantMessage('PHP is a server-side scripting language.'),
            new UserMessage('Tell me more.'),
        ];

        $formatted = $this->formatter->format($messages, null);

        $this->assertCount(3, $formatted['contents']);
        $this->assertSame('user', $formatted['contents'][0]['role']);
        $this->assertSame('model', $formatted['contents'][1]['role']);
        $this->assertSame('user', $formatted['contents'][2]['role']);
    }

    public function testFormatSkipsSystemMessages(): void
    {
        $messages = [
            new SystemMessage('System instructions'),
            new UserMessage('Hello!'),
        ];

        $formatted = $this->formatter->format($messages, null);

        // System messages are skipped in contents (handled via systemInstruction)
        $this->assertCount(1, $formatted['contents']);
        $this->assertSame('user', $formatted['contents'][0]['role']);
    }

    public function testFormatWithPartsStructure(): void
    {
        $messages = [
            new UserMessage('Test message'),
        ];

        $formatted = $this->formatter->format($messages, null);

        $this->assertArrayHasKey('parts', $formatted['contents'][0]);
        $this->assertIsArray($formatted['contents'][0]['parts']);
        $this->assertSame('Test message', $formatted['contents'][0]['parts'][0]['text']);
    }
}
