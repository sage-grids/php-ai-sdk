<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Core\Tool\ToolExecutionPolicy;
use SageGrids\PhpAiSdk\Core\Tool\ToolExecutor;
use SageGrids\PhpAiSdk\Exception\ToolSecurityException;
use SageGrids\PhpAiSdk\Result\ToolCall;

final class ToolExecutorWithPolicyTest extends TestCase
{
    private Tool $testTool;

    protected function setUp(): void
    {
        $this->testTool = Tool::create(
            'test_tool',
            'A test tool',
            Schema::object([
                'input' => Schema::string()->description('Input value'),
            ]),
            fn(array $args) => 'Result: ' . ($args['input'] ?? 'none'),
        );
    }

    public function testExecuteWithoutPolicyAllowsAll(): void
    {
        $executor = new ToolExecutor();
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Result: hello', $result->result);
    }

    public function testExecuteWithAllowedTool(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['test_tool']);

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Result: hello', $result->result);
    }

    public function testExecuteWithDeniedToolReturnsError(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['test_tool']);

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(ToolSecurityException::class, $result->error);
        $this->assertStringContainsString('explicitly denied', $result->getErrorMessage());
    }

    public function testExecuteWithNotAllowedToolReturnsError(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['other_tool']);

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(ToolSecurityException::class, $result->error);
        $this->assertStringContainsString('not allowed', $result->getErrorMessage());
    }

    public function testExecuteWithConfirmationDeniedReturnsError(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(fn() => false);

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(ToolSecurityException::class, $result->error);
        $this->assertStringContainsString('denied by confirmation', $result->getErrorMessage());
    }

    public function testExecuteWithConfirmationApprovedSucceeds(): void
    {
        $confirmationCalled = false;

        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(function () use (&$confirmationCalled): bool {
                $confirmationCalled = true;
                return true;
            });

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertTrue($confirmationCalled);
        $this->assertTrue($result->isSuccess());
    }

    public function testExecuteWithFailOnViolationThrowsException(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['test_tool'])
            ->failOnViolation(true);

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $this->expectException(ToolSecurityException::class);
        $this->expectExceptionMessage('explicitly denied');

        $executor->execute($this->testTool, $call);
    }

    public function testExecuteWithArgumentSanitizer(): void
    {
        $capturedArgs = null;

        $tool = Tool::create(
            'test_tool',
            'A test tool',
            Schema::object([
                'safe' => Schema::string()->optional(),
            ]),
            function (array $args) use (&$capturedArgs): string {
                $capturedArgs = $args;
                return 'ok';
            },
        );

        $policy = ToolExecutionPolicy::create()
            ->withArgumentSanitizer(function (string $name, array $args): array {
                // Remove 'sensitive' key
                unset($args['sensitive']);
                return $args;
            });

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['safe' => 'value', 'sensitive' => 'secret']);

        $result = $executor->execute($tool, $call);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['safe' => 'value'], $capturedArgs);
    }

    public function testSetAndGetPolicy(): void
    {
        $executor = new ToolExecutor();
        $this->assertNull($executor->getPolicy());

        $policy = ToolExecutionPolicy::create();
        $executor->setPolicy($policy);

        $this->assertSame($policy, $executor->getPolicy());
    }

    public function testSetPolicyReturnsSelf(): void
    {
        $executor = new ToolExecutor();
        $result = $executor->setPolicy(null);

        $this->assertSame($executor, $result);
    }

    public function testConstructorWithPolicy(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['denied_tool']);

        $executor = new ToolExecutor($policy);

        $this->assertSame($policy, $executor->getPolicy());
    }

    public function testToolSpecificConfirmation(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(function (string $name): bool {
                // Only allow specific tools
                return in_array($name, ['safe_tool', 'test_tool'], true);
            });

        $executor = new ToolExecutor($policy);

        // Test with allowed tool
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);
        $result = $executor->execute($this->testTool, $call);
        $this->assertTrue($result->isSuccess());
    }

    public function testArgumentBasedConfirmation(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(function (string $name, array $args): bool {
                // Deny if arguments contain 'dangerous' value
                return !in_array('dangerous', $args, true);
            });

        $executor = new ToolExecutor($policy);

        // Test with safe arguments
        $safeCall = new ToolCall('call_1', 'test_tool', ['input' => 'safe']);
        $safeResult = $executor->execute($this->testTool, $safeCall);
        $this->assertTrue($safeResult->isSuccess());

        // Test with dangerous arguments
        $dangerousCall = new ToolCall('call_2', 'test_tool', ['input' => 'dangerous']);
        $dangerousResult = $executor->execute($this->testTool, $dangerousCall);
        $this->assertFalse($dangerousResult->isSuccess());
    }

    public function testRestrictivePolicyDeniesAll(): void
    {
        $policy = ToolExecutionPolicy::restrictive();

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(ToolSecurityException::class, $result->error);
    }

    public function testRestrictivePolicyWithExplicitAllow(): void
    {
        $policy = ToolExecutionPolicy::restrictive()
            ->addAllowedTools(['test_tool']);

        $executor = new ToolExecutor($policy);
        $call = new ToolCall('call_1', 'test_tool', ['input' => 'hello']);

        $result = $executor->execute($this->testTool, $call);

        $this->assertTrue($result->isSuccess());
    }
}
