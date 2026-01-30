<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\ToolSecurityException;

final class ToolSecurityExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new ToolSecurityException(
            'Test message',
            'test_tool',
            ['arg' => 'value'],
            'test_reason',
            42
        );

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals('test_tool', $exception->toolName);
        $this->assertEquals(['arg' => 'value'], $exception->arguments);
        $this->assertEquals('test_reason', $exception->reason);
        $this->assertEquals(42, $exception->getCode());
    }

    public function testNotAllowed(): void
    {
        $exception = ToolSecurityException::notAllowed(
            'forbidden_tool',
            ['key' => 'value'],
            ['allowed_a', 'allowed_b']
        );

        $this->assertStringContainsString('forbidden_tool', $exception->getMessage());
        $this->assertStringContainsString('not allowed', $exception->getMessage());
        $this->assertStringContainsString('allowed_a', $exception->getMessage());
        $this->assertEquals('forbidden_tool', $exception->toolName);
        $this->assertEquals(['key' => 'value'], $exception->arguments);
        $this->assertEquals('not_in_allowed_list', $exception->reason);
    }

    public function testNotAllowedWithNullAllowedList(): void
    {
        $exception = ToolSecurityException::notAllowed('forbidden_tool', [], null);

        $this->assertStringContainsString('none specified', $exception->getMessage());
    }

    public function testExplicitlyDenied(): void
    {
        $exception = ToolSecurityException::explicitlyDenied(
            'denied_tool',
            ['key' => 'value']
        );

        $this->assertStringContainsString('denied_tool', $exception->getMessage());
        $this->assertStringContainsString('explicitly denied', $exception->getMessage());
        $this->assertEquals('denied_tool', $exception->toolName);
        $this->assertEquals(['key' => 'value'], $exception->arguments);
        $this->assertEquals('explicitly_denied', $exception->reason);
    }

    public function testConfirmationDenied(): void
    {
        $exception = ToolSecurityException::confirmationDenied(
            'unconfirmed_tool',
            ['key' => 'value']
        );

        $this->assertStringContainsString('unconfirmed_tool', $exception->getMessage());
        $this->assertStringContainsString('confirmation callback', $exception->getMessage());
        $this->assertEquals('unconfirmed_tool', $exception->toolName);
        $this->assertEquals(['key' => 'value'], $exception->arguments);
        $this->assertEquals('confirmation_denied', $exception->reason);
    }

    public function testTimeout(): void
    {
        $exception = ToolSecurityException::timeout(
            'slow_tool',
            ['key' => 'value'],
            30
        );

        $this->assertStringContainsString('slow_tool', $exception->getMessage());
        $this->assertStringContainsString('timed out', $exception->getMessage());
        $this->assertStringContainsString('30', $exception->getMessage());
        $this->assertEquals('slow_tool', $exception->toolName);
        $this->assertEquals(['key' => 'value'], $exception->arguments);
        $this->assertEquals('timeout', $exception->reason);
    }

    public function testToArray(): void
    {
        $exception = new ToolSecurityException(
            'Test message',
            'test_tool',
            ['arg' => 'value'],
            'test_reason'
        );

        $array = $exception->toArray();

        $this->assertEquals(ToolSecurityException::class, $array['type']);
        $this->assertEquals('Test message', $array['message']);
        $this->assertEquals('test_tool', $array['toolName']);
        $this->assertEquals(['arg' => 'value'], $array['arguments']);
        $this->assertEquals('test_reason', $array['reason']);
    }
}
