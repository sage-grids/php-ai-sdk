<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Exception\AIException;

final class AIExceptionTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new AIException('Test error', 500, $previous);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructWithDefaults(): void
    {
        $exception = new AIException();

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testCreate(): void
    {
        $exception = AIException::create('Created error', 100);

        $this->assertInstanceOf(AIException::class, $exception);
        $this->assertEquals('Created error', $exception->getMessage());
        $this->assertEquals(100, $exception->getCode());
    }

    public function testFromPrevious(): void
    {
        $previous = new RuntimeException('Original error', 42);
        $exception = AIException::fromPrevious('Context message', $previous);

        $this->assertEquals('Context message: Original error', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testGetExceptionChain(): void
    {
        $root = new RuntimeException('Root error', 1);
        $middle = new AIException('Middle error', 2, $root);
        $top = new AIException('Top error', 3, $middle);

        $chain = $top->getExceptionChain();

        $this->assertCount(3, $chain);
        $this->assertEquals('Top error', $chain[0]['message']);
        $this->assertEquals('Middle error', $chain[1]['message']);
        $this->assertEquals('Root error', $chain[2]['message']);
    }

    public function testGetExceptionChainSingleException(): void
    {
        $exception = new AIException('Single error');

        $chain = $exception->getExceptionChain();

        $this->assertCount(1, $chain);
        $this->assertEquals('Single error', $chain[0]['message']);
    }

    public function testToArray(): void
    {
        $exception = new AIException('Test error', 100);

        $array = $exception->toArray();

        $this->assertEquals(AIException::class, $array['type']);
        $this->assertEquals('Test error', $array['message']);
        $this->assertEquals(100, $array['code']);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('chain', $array);
        $this->assertCount(1, $array['chain']);
    }
}
