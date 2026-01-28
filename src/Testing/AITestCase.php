<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Testing;

use PHPUnit\Framework\Assert;
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextResult;

/**
 * PHPUnit trait providing helper methods for testing AI SDK functionality.
 *
 * This trait provides convenient methods for setting up fake providers,
 * making assertions about AI responses, and managing test state.
 *
 * @example
 * ```php
 * use PHPUnit\Framework\TestCase;
 * use SageGrids\PhpAiSdk\Testing\AITestCase;
 * use SageGrids\PhpAiSdk\AI;
 *
 * class MyFeatureTest extends TestCase
 * {
 *     use AITestCase;
 *
 *     public function testMyFeature(): void
 *     {
 *         // Set up fake provider
 *         $fake = $this->fake();
 *         $fake->addTextResponse('Expected response');
 *
 *         // Call your code that uses AI
 *         $result = AI::generateText([
 *             'model' => $fake,
 *             'prompt' => 'Test prompt',
 *         ]);
 *
 *         // Make assertions
 *         $this->assertAIGenerated($result, 'Expected response');
 *         $this->assertAIRequestMade($fake, 'generateText');
 *     }
 * }
 * ```
 */
trait AITestCase
{
    /**
     * @var FakeProvider[] Created fake providers for cleanup.
     */
    private array $fakeProviders = [];

    /**
     * Set up AI testing environment.
     *
     * Call this in your setUp() method to reset AI configuration.
     */
    protected function setUpAI(): void
    {
        AIConfig::reset();
        ProviderRegistry::resetInstance();
        $this->fakeProviders = [];
    }

    /**
     * Tear down AI testing environment.
     *
     * Call this in your tearDown() method to clean up.
     */
    protected function tearDownAI(): void
    {
        AIConfig::reset();
        ProviderRegistry::resetInstance();
        $this->fakeProviders = [];
    }

    /**
     * Create a new FakeProvider instance.
     *
     * Optionally registers the provider with the ProviderRegistry for use
     * with model strings like 'fake/model-name'.
     *
     * @param string $name Provider name (default: 'fake').
     * @param bool $register Whether to register with ProviderRegistry.
     * @return FakeProvider
     */
    protected function fake(string $name = 'fake', bool $register = true): FakeProvider
    {
        $provider = new FakeProvider($name);
        $this->fakeProviders[] = $provider;

        if ($register) {
            ProviderRegistry::getInstance()->register($name, $provider);
        }

        return $provider;
    }

    /**
     * Create a FakeProvider with pre-configured text responses.
     *
     * @param string|string[] $responses One or more text responses to queue.
     * @param string $name Provider name.
     * @return FakeProvider
     */
    protected function fakeWithTextResponses(string|array $responses, string $name = 'fake'): FakeProvider
    {
        $fake = $this->fake($name);

        $responses = is_array($responses) ? $responses : [$responses];
        foreach ($responses as $response) {
            $fake->addTextResponse($response);
        }

        return $fake;
    }

    /**
     * Create a FakeProvider with pre-configured object responses.
     *
     * @param mixed|array<mixed> $responses One or more object responses to queue.
     * @param string $name Provider name.
     * @return FakeProvider
     */
    protected function fakeWithObjectResponses(mixed $responses, string $name = 'fake'): FakeProvider
    {
        $fake = $this->fake($name);

        $responses = is_array($responses) && !$this->isAssociativeArray($responses)
            ? $responses
            : [$responses];

        foreach ($responses as $response) {
            $fake->addObjectResponse($response);
        }

        return $fake;
    }

    /**
     * Assert that a text result contains expected text.
     *
     * @param TextResult $result The result to check.
     * @param string $expectedText The expected text content.
     * @param string $message Optional assertion message.
     */
    protected function assertAIGenerated(
        TextResult $result,
        string $expectedText,
        string $message = '',
    ): void {
        Assert::assertEquals(
            $expectedText,
            $result->text,
            $message ?: "AI did not generate expected text.",
        );
    }

    /**
     * Assert that a text result contains a substring.
     *
     * @param TextResult $result The result to check.
     * @param string $needle The substring to search for.
     * @param string $message Optional assertion message.
     */
    protected function assertAIGeneratedContains(
        TextResult $result,
        string $needle,
        string $message = '',
    ): void {
        Assert::assertStringContainsString(
            $needle,
            $result->text,
            $message ?: "AI response does not contain expected text.",
        );
    }

    /**
     * Assert that an object result matches expected object.
     *
     * @param ObjectResult<mixed> $result The result to check.
     * @param mixed $expectedObject The expected object.
     * @param string $message Optional assertion message.
     */
    protected function assertAIGeneratedObject(
        ObjectResult $result,
        mixed $expectedObject,
        string $message = '',
    ): void {
        Assert::assertEquals(
            $expectedObject,
            $result->object,
            $message ?: "AI did not generate expected object.",
        );
    }

    /**
     * Assert that an object result contains expected keys.
     *
     * @param ObjectResult<mixed> $result The result to check.
     * @param string[] $keys The expected keys.
     * @param string $message Optional assertion message.
     */
    protected function assertAIGeneratedObjectHasKeys(
        ObjectResult $result,
        array $keys,
        string $message = '',
    ): void {
        Assert::assertIsArray($result->object, "AI result object is not an array.");

        foreach ($keys as $key) {
            Assert::assertArrayHasKey(
                $key,
                $result->object,
                $message ?: "AI object missing expected key: {$key}",
            );
        }
    }

    /**
     * Assert that a result completed successfully.
     *
     * @param TextResult|ObjectResult<mixed> $result The result to check.
     * @param string $message Optional assertion message.
     */
    protected function assertAICompleted(
        TextResult|ObjectResult $result,
        string $message = '',
    ): void {
        Assert::assertEquals(
            FinishReason::Stop,
            $result->finishReason,
            $message ?: "AI did not complete successfully. Reason: " . ($result->finishReason?->value ?? 'null'),
        );
    }

    /**
     * Assert that a result has tool calls.
     *
     * @param TextResult $result The result to check.
     * @param int|null $count Expected number of tool calls (null for any).
     * @param string $message Optional assertion message.
     */
    protected function assertAIHasToolCalls(
        TextResult $result,
        ?int $count = null,
        string $message = '',
    ): void {
        Assert::assertTrue(
            $result->hasToolCalls(),
            $message ?: "AI result does not have tool calls.",
        );

        if ($count !== null) {
            Assert::assertCount(
                $count,
                $result->toolCalls,
                $message ?: "AI result has unexpected number of tool calls.",
            );
        }
    }

    /**
     * Assert that a specific tool was called.
     *
     * @param TextResult $result The result to check.
     * @param string $toolName The expected tool name.
     * @param array<string, mixed>|null $arguments Expected arguments (null for any).
     * @param string $message Optional assertion message.
     */
    protected function assertAICalledTool(
        TextResult $result,
        string $toolName,
        ?array $arguments = null,
        string $message = '',
    ): void {
        $found = false;
        foreach ($result->toolCalls as $call) {
            if ($call->name === $toolName) {
                $found = true;
                if ($arguments !== null) {
                    Assert::assertEquals(
                        $arguments,
                        $call->arguments,
                        $message ?: "Tool '{$toolName}' called with unexpected arguments.",
                    );
                }
                break;
            }
        }

        Assert::assertTrue(
            $found,
            $message ?: "Tool '{$toolName}' was not called. Called tools: " .
                implode(', ', array_map(fn ($c) => $c->name, $result->toolCalls)),
        );
    }

    /**
     * Assert that a request was made to the fake provider.
     *
     * @param FakeProvider $fake The fake provider.
     * @param string $operation The expected operation.
     * @param callable|null $assertion Optional callback to validate request.
     * @param string $message Optional assertion message.
     */
    protected function assertAIRequestMade(
        FakeProvider $fake,
        string $operation,
        ?callable $assertion = null,
        string $message = '',
    ): void {
        try {
            $fake->assertRequestMade($operation, $assertion);
            Assert::assertTrue(true, 'Request was made to ' . $operation);
        } catch (\InvalidArgumentException $e) {
            Assert::fail($message ?: $e->getMessage());
        }
    }

    /**
     * Assert that a specific number of requests were made.
     *
     * @param FakeProvider $fake The fake provider.
     * @param int $count Expected number of requests.
     * @param string|null $operation Optional operation to filter by.
     * @param string $message Optional assertion message.
     */
    protected function assertAIRequestCount(
        FakeProvider $fake,
        int $count,
        ?string $operation = null,
        string $message = '',
    ): void {
        try {
            $fake->assertRequestCount($count, $operation);
            Assert::assertTrue(true, "Request count matches expected: {$count}");
        } catch (\InvalidArgumentException $e) {
            Assert::fail($message ?: $e->getMessage());
        }
    }

    /**
     * Assert that no requests were made.
     *
     * @param FakeProvider $fake The fake provider.
     * @param string $message Optional assertion message.
     */
    protected function assertNoAIRequests(FakeProvider $fake, string $message = ''): void
    {
        Assert::assertCount(
            0,
            $fake->getRequests(),
            $message ?: "Expected no AI requests, but found " . count($fake->getRequests()),
        );
    }

    /**
     * Assert that a request contained specific message content.
     *
     * @param FakeProvider $fake The fake provider.
     * @param string $content The expected content.
     * @param string $message Optional assertion message.
     */
    protected function assertAIRequestContains(
        FakeProvider $fake,
        string $content,
        string $message = '',
    ): void {
        $lastRequest = $fake->getLastRequest();
        Assert::assertNotNull($lastRequest, "No AI requests were made.");
        Assert::assertTrue(
            $lastRequest->hasMessageContent($content),
            $message ?: "Last AI request does not contain expected content: {$content}",
        );
    }

    /**
     * Assert that a request used specific parameters.
     *
     * @param FakeProvider $fake The fake provider.
     * @param array<string, mixed> $params Expected parameters.
     * @param string $message Optional assertion message.
     */
    protected function assertAIRequestParams(
        FakeProvider $fake,
        array $params,
        string $message = '',
    ): void {
        $lastRequest = $fake->getLastRequest();
        Assert::assertNotNull($lastRequest, "No AI requests were made.");

        foreach ($params as $key => $value) {
            $actual = match ($key) {
                'system' => $lastRequest->system,
                'maxTokens' => $lastRequest->maxTokens,
                'temperature' => $lastRequest->temperature,
                'topP' => $lastRequest->topP,
                'stopSequences' => $lastRequest->stopSequences,
                default => $lastRequest->extraParams[$key] ?? null,
            };

            Assert::assertEquals(
                $value,
                $actual,
                $message ?: "Request parameter '{$key}' has unexpected value.",
            );
        }
    }

    /**
     * Check if an array is associative.
     *
     * @param array<mixed> $arr The array to check.
     * @return bool True if associative.
     */
    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
