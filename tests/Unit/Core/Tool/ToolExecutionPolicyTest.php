<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Tool\ToolExecutionPolicy;
use SageGrids\PhpAiSdk\Result\ToolCall;

final class ToolExecutionPolicyTest extends TestCase
{
    public function testCreateReturnsNewInstance(): void
    {
        $policy = ToolExecutionPolicy::create();
        $this->assertInstanceOf(ToolExecutionPolicy::class, $policy);
    }

    public function testRestrictivePolicyDeniesAllByDefault(): void
    {
        $policy = ToolExecutionPolicy::restrictive();
        $this->assertFalse($policy->isToolAllowed('any_tool'));
    }

    public function testDefaultPolicyAllowsAllTools(): void
    {
        $policy = ToolExecutionPolicy::create();
        $this->assertTrue($policy->isToolAllowed('any_tool'));
        $this->assertTrue($policy->isToolAllowed('another_tool'));
    }

    public function testAllowToolsRestrictsToList(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['tool_a', 'tool_b']);

        $this->assertTrue($policy->isToolAllowed('tool_a'));
        $this->assertTrue($policy->isToolAllowed('tool_b'));
        $this->assertFalse($policy->isToolAllowed('tool_c'));
    }

    public function testAddAllowedToolsAppendsToList(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['tool_a'])
            ->addAllowedTools(['tool_b']);

        $this->assertTrue($policy->isToolAllowed('tool_a'));
        $this->assertTrue($policy->isToolAllowed('tool_b'));
        $this->assertFalse($policy->isToolAllowed('tool_c'));
    }

    public function testAddAllowedToolsCreatesListIfNone(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->addAllowedTools(['tool_a']);

        $this->assertTrue($policy->isToolAllowed('tool_a'));
        $this->assertFalse($policy->isToolAllowed('tool_b'));
    }

    public function testDenyToolsBlocksSpecificTools(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['dangerous_tool']);

        $this->assertTrue($policy->isToolAllowed('safe_tool'));
        $this->assertFalse($policy->isToolAllowed('dangerous_tool'));
    }

    public function testDenyTakesPrecedenceOverAllow(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['tool_a', 'tool_b', 'tool_c'])
            ->denyTools(['tool_b']);

        $this->assertTrue($policy->isToolAllowed('tool_a'));
        $this->assertFalse($policy->isToolAllowed('tool_b'));
        $this->assertTrue($policy->isToolAllowed('tool_c'));
    }

    public function testAddDeniedToolsAppendsToList(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['tool_a'])
            ->addDeniedTools(['tool_b']);

        $this->assertFalse($policy->isToolAllowed('tool_a'));
        $this->assertFalse($policy->isToolAllowed('tool_b'));
        $this->assertTrue($policy->isToolAllowed('tool_c'));
    }

    public function testConfirmationCallbackIsInvoked(): void
    {
        $callbackInvoked = false;
        $capturedName = null;
        $capturedArgs = null;

        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(function (string $name, array $args) use (&$callbackInvoked, &$capturedName, &$capturedArgs): bool {
                $callbackInvoked = true;
                $capturedName = $name;
                $capturedArgs = $args;
                return true;
            });

        $result = $policy->confirmExecution('test_tool', ['key' => 'value']);

        $this->assertTrue($callbackInvoked);
        $this->assertEquals('test_tool', $capturedName);
        $this->assertEquals(['key' => 'value'], $capturedArgs);
        $this->assertTrue($result);
    }

    public function testConfirmationCallbackCanDeny(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(fn() => false);

        $this->assertFalse($policy->confirmExecution('test_tool', []));
    }

    public function testNoConfirmationCallbackReturnsTrueByDefault(): void
    {
        $policy = ToolExecutionPolicy::create();
        $this->assertTrue($policy->confirmExecution('any_tool', []));
    }

    public function testTimeoutConfiguration(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withTimeout(30);

        $this->assertEquals(30, $policy->getTimeout());
    }

    public function testNullTimeoutByDefault(): void
    {
        $policy = ToolExecutionPolicy::create();
        $this->assertNull($policy->getTimeout());
    }

    public function testArgumentSanitizer(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withArgumentSanitizer(function (string $name, array $args): array {
                // Remove sensitive keys
                unset($args['password']);
                return $args;
            });

        $sanitized = $policy->sanitizeArguments('tool', ['username' => 'test', 'password' => 'secret']);

        $this->assertEquals(['username' => 'test'], $sanitized);
    }

    public function testNoSanitizerReturnsOriginalArgs(): void
    {
        $policy = ToolExecutionPolicy::create();
        $args = ['key' => 'value'];

        $this->assertEquals($args, $policy->sanitizeArguments('tool', $args));
    }

    public function testFailOnViolation(): void
    {
        $policy = ToolExecutionPolicy::create();
        $this->assertFalse($policy->shouldFailOnViolation());

        $policy = $policy->failOnViolation(true);
        $this->assertTrue($policy->shouldFailOnViolation());

        $policy = $policy->failOnViolation(false);
        $this->assertFalse($policy->shouldFailOnViolation());
    }

    public function testValidateReturnsNullForAllowedTool(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['allowed_tool']);

        $call = new ToolCall('call_1', 'allowed_tool', []);
        $this->assertNull($policy->validate($call));
    }

    public function testValidateReturnsErrorForDeniedTool(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['denied_tool']);

        $call = new ToolCall('call_1', 'denied_tool', []);
        $error = $policy->validate($call);

        $this->assertNotNull($error);
        $this->assertStringContainsString('denied_tool', $error);
        $this->assertStringContainsString('explicitly denied', $error);
    }

    public function testValidateReturnsErrorForNotAllowedTool(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['allowed_tool']);

        $call = new ToolCall('call_1', 'other_tool', []);
        $error = $policy->validate($call);

        $this->assertNotNull($error);
        $this->assertStringContainsString('other_tool', $error);
        $this->assertStringContainsString('not in the allowed', $error);
    }

    public function testValidateReturnsErrorForConfirmationDenied(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->withConfirmation(fn() => false);

        $call = new ToolCall('call_1', 'any_tool', []);
        $error = $policy->validate($call);

        $this->assertNotNull($error);
        $this->assertStringContainsString('confirmation callback', $error);
    }

    public function testHasRestrictions(): void
    {
        $policy = ToolExecutionPolicy::create();
        $this->assertFalse($policy->hasRestrictions());

        $this->assertTrue(
            ToolExecutionPolicy::create()->allowTools(['tool'])->hasRestrictions()
        );

        $this->assertTrue(
            ToolExecutionPolicy::create()->denyTools(['tool'])->hasRestrictions()
        );

        $this->assertTrue(
            ToolExecutionPolicy::create()->withConfirmation(fn() => true)->hasRestrictions()
        );

        $this->assertTrue(
            ToolExecutionPolicy::create()->withTimeout(30)->hasRestrictions()
        );

        $this->assertTrue(
            ToolExecutionPolicy::create()->withArgumentSanitizer(fn($n, $a) => $a)->hasRestrictions()
        );
    }

    public function testImmutability(): void
    {
        $original = ToolExecutionPolicy::create();
        $modified = $original->allowTools(['tool']);

        $this->assertNotSame($original, $modified);
        $this->assertTrue($original->isToolAllowed('any_tool'));
        $this->assertFalse($modified->isToolAllowed('any_tool'));
    }

    public function testGetAllowedTools(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->allowTools(['tool_a', 'tool_b']);

        $this->assertEquals(['tool_a', 'tool_b'], $policy->getAllowedTools());
    }

    public function testGetDeniedTools(): void
    {
        $policy = ToolExecutionPolicy::create()
            ->denyTools(['tool_x', 'tool_y']);

        $this->assertEquals(['tool_x', 'tool_y'], $policy->getDeniedTools());
    }

    public function testAllowToolsWithNull(): void
    {
        $policy = ToolExecutionPolicy::restrictive()
            ->allowTools(null);

        $this->assertTrue($policy->isToolAllowed('any_tool'));
        $this->assertNull($policy->getAllowedTools());
    }
}
