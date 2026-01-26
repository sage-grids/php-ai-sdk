<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Exception\AIException;
use SageGrids\PhpAiSdk\Exception\TimeoutException;

final class TimeoutExceptionTest extends TestCase
{
    public function testIsAIException(): void
    {
        $exception = new TimeoutException('Timed out');

        $this->assertInstanceOf(AIException::class, $exception);
    }

    public function testConstructWithAllParameters(): void
    {
        $previous = new RuntimeException('Previous');

        $exception = new TimeoutException(
            'Connection timed out',
            'connection',
            30.5,
            100,
            $previous,
        );

        $this->assertEquals('Connection timed out', $exception->getMessage());
        $this->assertEquals('connection', $exception->operation);
        $this->assertEquals(30.5, $exception->timeoutSeconds);
        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructWithDefaults(): void
    {
        $exception = new TimeoutException('Timed out');

        $this->assertEquals('operation', $exception->operation);
        $this->assertNull($exception->timeoutSeconds);
    }

    public function testConnectionTimeout(): void
    {
        $exception = TimeoutException::connectionTimeout('api.openai.com', 10);

        $this->assertStringContainsString('api.openai.com', $exception->getMessage());
        $this->assertStringContainsString('10', $exception->getMessage());
        $this->assertEquals('connection', $exception->operation);
        $this->assertEquals(10, $exception->timeoutSeconds);
    }

    public function testReadTimeout(): void
    {
        $exception = TimeoutException::readTimeout(30);

        $this->assertStringContainsString('Read operation', $exception->getMessage());
        $this->assertStringContainsString('30', $exception->getMessage());
        $this->assertEquals('read', $exception->operation);
        $this->assertEquals(30, $exception->timeoutSeconds);
    }

    public function testRequestTimeout(): void
    {
        $exception = TimeoutException::requestTimeout('https://api.example.com/chat', 60);

        $this->assertStringContainsString('https://api.example.com/chat', $exception->getMessage());
        $this->assertEquals('request', $exception->operation);
        $this->assertEquals(60, $exception->timeoutSeconds);
    }

    public function testStreamingTimeout(): void
    {
        $exception = TimeoutException::streamingTimeout(120);

        $this->assertStringContainsString('Streaming operation', $exception->getMessage());
        $this->assertEquals('streaming', $exception->operation);
        $this->assertEquals(120, $exception->timeoutSeconds);
    }

    public function testOperationTimeout(): void
    {
        $exception = TimeoutException::operationTimeout('embedding generation', 45);

        $this->assertStringContainsString('Embedding generation', $exception->getMessage()); // Ucfirst
        $this->assertEquals('embedding generation', $exception->operation);
        $this->assertEquals(45, $exception->timeoutSeconds);
    }

    public function testToArray(): void
    {
        $exception = new TimeoutException('Timed out', 'connection', 30);

        $array = $exception->toArray();

        $this->assertEquals('connection', $array['operation']);
        $this->assertEquals(30, $array['timeoutSeconds']);
    }

    public function testFloatTimeout(): void
    {
        $exception = TimeoutException::connectionTimeout('host', 1.5);

        $this->assertStringContainsString('1.5', $exception->getMessage());
        $this->assertEquals(1.5, $exception->timeoutSeconds);
    }
}
