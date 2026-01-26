<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Exception\AIException;
use SageGrids\PhpAiSdk\Exception\ToolExecutionException;

final class ToolExecutionExceptionTest extends TestCase
{
    public function testIsAIException(): void
    {
        $exception = new ToolExecutionException('Failed', 'myTool');

        $this->assertInstanceOf(AIException::class, $exception);
    }

    public function testConstructWithAllParameters(): void
    {
        $original = new RuntimeException('Original error', 42);
        $arguments = ['param1' => 'value1', 'param2' => 123];

        $exception = new ToolExecutionException(
            'Tool failed',
            'calculator',
            $arguments,
            $original,
            100,
        );

        $this->assertEquals('Tool failed', $exception->getMessage());
        $this->assertEquals('calculator', $exception->toolName);
        $this->assertEquals($arguments, $exception->arguments);
        $this->assertSame($original, $exception->originalException);
        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($original, $exception->getPrevious());
    }

    public function testFromException(): void
    {
        $original = new RuntimeException('Division by zero', 42);
        $arguments = ['a' => 10, 'b' => 0];

        $exception = ToolExecutionException::fromException('divide', $arguments, $original);

        $this->assertStringContainsString('divide', $exception->getMessage());
        $this->assertStringContainsString('Division by zero', $exception->getMessage());
        $this->assertEquals('divide', $exception->toolName);
        $this->assertEquals($arguments, $exception->arguments);
        $this->assertSame($original, $exception->originalException);
        $this->assertEquals(42, $exception->getCode());
    }

    public function testToolNotFound(): void
    {
        $exception = ToolExecutionException::toolNotFound('nonexistentTool');

        $this->assertStringContainsString('nonexistentTool', $exception->getMessage());
        $this->assertStringContainsString('not registered', $exception->getMessage());
        $this->assertEquals('nonexistentTool', $exception->toolName);
    }

    public function testInvalidArguments(): void
    {
        $arguments = ['wrong' => 'data'];

        $exception = ToolExecutionException::invalidArguments('myTool', $arguments, 'Missing required parameter');

        $this->assertStringContainsString('myTool', $exception->getMessage());
        $this->assertStringContainsString('Missing required parameter', $exception->getMessage());
        $this->assertEquals($arguments, $exception->arguments);
    }

    public function testTimeout(): void
    {
        $arguments = ['heavy' => 'computation'];

        $exception = ToolExecutionException::timeout('slowTool', $arguments, 30);

        $this->assertStringContainsString('slowTool', $exception->getMessage());
        $this->assertStringContainsString('timed out', $exception->getMessage());
        $this->assertStringContainsString('30', $exception->getMessage());
    }

    public function testInvalidOutput(): void
    {
        $output = ['invalid' => 'structure'];

        $exception = ToolExecutionException::invalidOutput('parser', $output, 'Expected string output');

        $this->assertStringContainsString('parser', $exception->getMessage());
        $this->assertStringContainsString('Expected string output', $exception->getMessage());
        $this->assertEquals(['output' => $output], $exception->arguments);
    }

    public function testNotCallable(): void
    {
        $exception = ToolExecutionException::notCallable('brokenTool');

        $this->assertStringContainsString('brokenTool', $exception->getMessage());
        $this->assertStringContainsString('not callable', $exception->getMessage());
    }

    public function testToArray(): void
    {
        $original = new RuntimeException('Original');
        $exception = new ToolExecutionException(
            'Failed',
            'testTool',
            ['arg' => 'value'],
            $original,
        );

        $array = $exception->toArray();

        $this->assertEquals('testTool', $array['toolName']);
        $this->assertEquals(['arg' => 'value'], $array['arguments']);
        $this->assertArrayHasKey('originalException', $array);
        $this->assertEquals(RuntimeException::class, $array['originalException']['class']);
        $this->assertEquals('Original', $array['originalException']['message']);
    }

    public function testToArrayWithoutOriginalException(): void
    {
        $exception = new ToolExecutionException('Failed', 'testTool');

        $array = $exception->toArray();

        $this->assertArrayNotHasKey('originalException', $array);
    }
}
