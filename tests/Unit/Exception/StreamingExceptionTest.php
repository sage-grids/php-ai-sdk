<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Exception\AIException;
use SageGrids\PhpAiSdk\Exception\StreamingException;

final class StreamingExceptionTest extends TestCase
{
    public function testIsAIException(): void
    {
        $exception = new StreamingException('Stream failed');

        $this->assertInstanceOf(AIException::class, $exception);
    }

    public function testConstructWithAllParameters(): void
    {
        $previous = new RuntimeException('Previous');

        $exception = new StreamingException(
            'Stream error',
            'data',
            '{"partial": "json"',
            100,
            $previous,
        );

        $this->assertEquals('Stream error', $exception->getMessage());
        $this->assertEquals('data', $exception->eventType);
        $this->assertEquals('{"partial": "json"', $exception->lastData);
        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConnectionFailed(): void
    {
        $previous = new RuntimeException('Network error');

        $exception = StreamingException::connectionFailed('Connection reset', $previous);

        $this->assertStringContainsString('Connection reset', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testUnexpectedTermination(): void
    {
        $exception = StreamingException::unexpectedTermination('{"incomplete": true');

        $this->assertStringContainsString('unexpectedly', $exception->getMessage());
        $this->assertEquals('{"incomplete": true', $exception->lastData);
    }

    public function testUnexpectedTerminationWithoutData(): void
    {
        $exception = StreamingException::unexpectedTermination();

        $this->assertNull($exception->lastData);
    }

    public function testInvalidEvent(): void
    {
        $exception = StreamingException::invalidEvent('custom_event', 'Unknown event type', '{"data": "test"}');

        $this->assertStringContainsString('custom_event', $exception->getMessage());
        $this->assertStringContainsString('Unknown event type', $exception->getMessage());
        $this->assertEquals('custom_event', $exception->eventType);
        $this->assertEquals('{"data": "test"}', $exception->lastData);
    }

    public function testChunkParsingFailed(): void
    {
        $previous = new RuntimeException('JSON parse error');

        $exception = StreamingException::chunkParsingFailed('invalid chunk data', 'Unexpected character', $previous);

        $this->assertStringContainsString('Unexpected character', $exception->getMessage());
        $this->assertEquals('invalid chunk data', $exception->lastData);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testInvalidJsonChunk(): void
    {
        $previous = new RuntimeException('Syntax error');

        $exception = StreamingException::invalidJsonChunk('{"broken": json}', $previous);

        $this->assertStringContainsString('invalid JSON', $exception->getMessage());
        $this->assertEquals('{"broken": json}', $exception->lastData);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testProviderError(): void
    {
        $errorData = ['code' => 'stream_error', 'message' => 'Internal error'];

        $exception = StreamingException::providerError('Internal streaming error', $errorData);

        $this->assertStringContainsString('Provider streaming error', $exception->getMessage());
        $this->assertStringContainsString('Internal streaming error', $exception->getMessage());
        $this->assertEquals('error', $exception->eventType);
        $this->assertNotNull($exception->lastData);
        $this->assertJson($exception->lastData);
    }

    public function testProviderErrorWithoutData(): void
    {
        $exception = StreamingException::providerError('Error message');

        $this->assertNull($exception->lastData);
    }

    public function testInterrupted(): void
    {
        $exception = StreamingException::interrupted('User cancelled');

        $this->assertStringContainsString('interrupted', $exception->getMessage());
        $this->assertStringContainsString('User cancelled', $exception->getMessage());
    }

    public function testNoData(): void
    {
        $exception = StreamingException::noData(30);

        $this->assertStringContainsString('No data received', $exception->getMessage());
        $this->assertStringContainsString('30', $exception->getMessage());
    }

    public function testToArray(): void
    {
        $exception = new StreamingException('Test', 'message', '{"test": true}');

        $array = $exception->toArray();

        $this->assertEquals('message', $array['eventType']);
        $this->assertEquals('{"test": true}', $array['lastData']);
    }
}
