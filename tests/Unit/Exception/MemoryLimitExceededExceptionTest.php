<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\MemoryLimitExceededException;

final class MemoryLimitExceededExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new MemoryLimitExceededException(
            'Test message',
            150,
            100,
            5,
        );

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(150, $exception->currentMessageCount);
        $this->assertEquals(100, $exception->maxMessages);
        $this->assertEquals(5, $exception->roundtripCount);
        $this->assertEquals(0, $exception->getCode());
    }

    public function testMessageLimitExceeded(): void
    {
        $exception = MemoryLimitExceededException::messageLimitExceeded(
            150,
            100,
            5,
        );

        $this->assertStringContainsString('150 messages', $exception->getMessage());
        $this->assertStringContainsString('max: 100', $exception->getMessage());
        $this->assertStringContainsString('5 tool roundtrips', $exception->getMessage());
        $this->assertStringContainsString('Consider increasing maxMessages', $exception->getMessage());
        $this->assertEquals(150, $exception->currentMessageCount);
        $this->assertEquals(100, $exception->maxMessages);
        $this->assertEquals(5, $exception->roundtripCount);
    }

    public function testApproachingLimit(): void
    {
        $exception = MemoryLimitExceededException::approachingLimit(
            80,
            100,
            3,
        );

        $this->assertStringContainsString('80/100 messages', $exception->getMessage());
        $this->assertStringContainsString('80%', $exception->getMessage());
        $this->assertStringContainsString('3 roundtrips', $exception->getMessage());
        $this->assertEquals(80, $exception->currentMessageCount);
        $this->assertEquals(100, $exception->maxMessages);
        $this->assertEquals(3, $exception->roundtripCount);
    }

    public function testWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new MemoryLimitExceededException(
            'Test message',
            150,
            100,
            5,
            $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
    }
}
