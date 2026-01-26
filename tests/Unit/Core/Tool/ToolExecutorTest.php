<?php

namespace Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Core\Tool\ToolExecutor;
use SageGrids\PhpAiSdk\Core\Tool\ToolRegistry;
use SageGrids\PhpAiSdk\Result\ToolCall;

final class ToolExecutorTest extends TestCase
{
    private ToolExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new ToolExecutor();
    }

    public function testExecuteSuccessfully(): void
    {
        $tool = Tool::create(
            name: 'greet',
            description: 'Greet someone',
            parameters: Schema::object([
                'name' => Schema::string(),
            ]),
            execute: fn (array $args) => "Hello, {$args['name']}!",
        );

        $call = new ToolCall(
            id: 'call_123',
            name: 'greet',
            arguments: ['name' => 'World'],
        );

        $result = $this->executor->execute($tool, $call);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('call_123', $result->toolCallId);
        $this->assertSame('Hello, World!', $result->result);
    }

    public function testExecuteWithError(): void
    {
        $tool = Tool::create(
            name: 'divide',
            description: 'Divide numbers',
            parameters: Schema::object([
                'a' => Schema::number(),
                'b' => Schema::number(),
            ]),
            execute: function (array $args) {
                if ($args['b'] === 0) {
                    throw new RuntimeException('Division by zero');
                }
                return $args['a'] / $args['b'];
            },
        );

        $call = new ToolCall(
            id: 'call_456',
            name: 'divide',
            arguments: ['a' => 10, 'b' => 0],
        );

        $result = $this->executor->execute($tool, $call);

        $this->assertTrue($result->isFailure());
        $this->assertSame('call_456', $result->toolCallId);
        $this->assertSame('Division by zero', $result->getErrorMessage());
    }

    public function testExecuteWithValidationError(): void
    {
        $tool = Tool::create(
            name: 'greet',
            description: 'Greet someone',
            parameters: Schema::object([
                'name' => Schema::string(),
            ]),
            execute: fn (array $args) => "Hello!",
        );

        $call = new ToolCall(
            id: 'call_789',
            name: 'greet',
            arguments: [], // Missing required 'name'
        );

        $result = $this->executor->execute($tool, $call);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('validation failed', $result->getErrorMessage());
    }

    public function testExecuteAllWithRegistry(): void
    {
        $registry = new ToolRegistry();

        $registry->register(Tool::create(
            name: 'add',
            description: 'Add numbers',
            parameters: Schema::object([
                'a' => Schema::integer(),
                'b' => Schema::integer(),
            ]),
            execute: fn (array $args) => $args['a'] + $args['b'],
        ));

        $registry->register(Tool::create(
            name: 'multiply',
            description: 'Multiply numbers',
            parameters: Schema::object([
                'a' => Schema::integer(),
                'b' => Schema::integer(),
            ]),
            execute: fn (array $args) => $args['a'] * $args['b'],
        ));

        $calls = [
            new ToolCall('call_1', 'add', ['a' => 2, 'b' => 3]),
            new ToolCall('call_2', 'multiply', ['a' => 4, 'b' => 5]),
        ];

        $results = $this->executor->executeAll($registry, $calls);

        $this->assertCount(2, $results);

        $this->assertTrue($results[0]->isSuccess());
        $this->assertSame(5, $results[0]->result);

        $this->assertTrue($results[1]->isSuccess());
        $this->assertSame(20, $results[1]->result);
    }

    public function testExecuteAllWithMissingTool(): void
    {
        $registry = new ToolRegistry();

        $calls = [
            new ToolCall('call_1', 'nonexistent', ['arg' => 'value']),
        ];

        $results = $this->executor->executeAll($registry, $calls);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isFailure());
        $this->assertStringContainsString('Tool not found', $results[0]->getErrorMessage());
    }

    public function testExecuteAllMixedResults(): void
    {
        $registry = new ToolRegistry();

        $registry->register(Tool::create(
            name: 'safe',
            description: 'Safe tool',
            parameters: Schema::object([]),
            execute: fn (array $args) => 'success',
        ));

        $registry->register(Tool::create(
            name: 'risky',
            description: 'Risky tool',
            parameters: Schema::object([]),
            execute: function (array $args) {
                throw new RuntimeException('Something went wrong');
            },
        ));

        $calls = [
            new ToolCall('call_1', 'safe', []),
            new ToolCall('call_2', 'risky', []),
            new ToolCall('call_3', 'missing', []),
        ];

        $results = $this->executor->executeAll($registry, $calls);

        $this->assertCount(3, $results);

        $this->assertTrue($results[0]->isSuccess());
        $this->assertSame('success', $results[0]->result);

        $this->assertTrue($results[1]->isFailure());
        $this->assertSame('Something went wrong', $results[1]->getErrorMessage());

        $this->assertTrue($results[2]->isFailure());
        $this->assertStringContainsString('Tool not found', $results[2]->getErrorMessage());
    }
}
