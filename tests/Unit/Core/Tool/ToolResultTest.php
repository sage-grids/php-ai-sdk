<?php

namespace Tests\Unit\Core\Tool;

use Exception;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Tool\ToolResult;

final class ToolResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $result = ToolResult::success('call_123', 'Weather: Sunny');

        $this->assertSame('call_123', $result->toolCallId);
        $this->assertSame('Weather: Sunny', $result->result);
        $this->assertNull($result->error);
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
    }

    public function testFailedResult(): void
    {
        $error = new Exception('API timeout');
        $result = ToolResult::failure('call_456', $error);

        $this->assertSame('call_456', $result->toolCallId);
        $this->assertNull($result->result);
        $this->assertSame($error, $result->error);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
    }

    public function testGetErrorMessage(): void
    {
        $error = new Exception('Something went wrong');
        $result = ToolResult::failure('call_789', $error);

        $this->assertSame('Something went wrong', $result->getErrorMessage());
    }

    public function testGetErrorMessageReturnsNullOnSuccess(): void
    {
        $result = ToolResult::success('call_123', 'data');

        $this->assertNull($result->getErrorMessage());
    }

    public function testToArrayWithSuccessfulStringResult(): void
    {
        $result = ToolResult::success('call_123', 'Hello World');
        $array = $result->toArray();

        $this->assertSame([
            'tool_call_id' => 'call_123',
            'content' => 'Hello World',
        ], $array);
    }

    public function testToArrayWithSuccessfulArrayResult(): void
    {
        $result = ToolResult::success('call_123', ['temperature' => 22, 'unit' => 'celsius']);
        $array = $result->toArray();

        $this->assertSame('call_123', $array['tool_call_id']);
        $this->assertSame('{"temperature":22,"unit":"celsius"}', $array['content']);
    }

    public function testToArrayWithFailedResult(): void
    {
        $error = new Exception('Tool execution failed');
        $result = ToolResult::failure('call_123', $error);
        $array = $result->toArray();

        $this->assertSame('call_123', $array['tool_call_id']);
        $this->assertSame('Error: Tool execution failed', $array['content']);
    }

    public function testConstructorDirectly(): void
    {
        $error = new Exception('Test error');
        $result = new ToolResult('call_direct', 'result_value', $error);

        $this->assertSame('call_direct', $result->toolCallId);
        $this->assertSame('result_value', $result->result);
        $this->assertSame($error, $result->error);
    }
}
