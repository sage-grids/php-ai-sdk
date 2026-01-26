<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\ProviderException;
use SageGrids\PhpAiSdk\Exception\RateLimitException;

final class RateLimitExceptionTest extends TestCase
{
    public function testIsProviderException(): void
    {
        $exception = new RateLimitException('Rate limited', 'openai');

        $this->assertInstanceOf(ProviderException::class, $exception);
    }

    public function testConstructWithRetryAfter(): void
    {
        $exception = new RateLimitException(
            'Rate limited',
            'openai',
            'gpt-4',
            429,
            ['type' => 'rate_limit'],
            'req_123',
            60,
        );

        $this->assertEquals('Rate limited', $exception->getMessage());
        $this->assertEquals('openai', $exception->provider);
        $this->assertEquals('gpt-4', $exception->model);
        $this->assertEquals(429, $exception->statusCode);
        $this->assertEquals(60, $exception->retryAfterSeconds);
    }

    public function testFromResponse(): void
    {
        $response = ['error' => ['message' => 'Too many requests'], 'retry_after' => 30];

        $exception = RateLimitException::fromResponse('openai', 429, $response, 'gpt-4', 'req_123');

        $this->assertEquals('Too many requests', $exception->getMessage());
        $this->assertEquals(30, $exception->retryAfterSeconds);
        $this->assertEquals('req_123', $exception->requestId);
    }

    public function testFromResponseWithNestedRetryAfter(): void
    {
        $response = ['error' => ['message' => 'Rate limited', 'retry_after' => 45]];

        $exception = RateLimitException::fromResponse('openai', 429, $response);

        $this->assertEquals(45, $exception->retryAfterSeconds);
    }

    public function testFromResponseWithoutRetryAfter(): void
    {
        $response = ['error' => ['message' => 'Rate limited']];

        $exception = RateLimitException::fromResponse('openai', 429, $response);

        $this->assertNull($exception->retryAfterSeconds);
    }

    public function testRequestsPerMinute(): void
    {
        $exception = RateLimitException::requestsPerMinute('openai', 100, 30);

        $this->assertStringContainsString('100 requests per minute', $exception->getMessage());
        $this->assertEquals(30, $exception->retryAfterSeconds);
    }

    public function testRequestsPerMinuteDefaultRetryAfter(): void
    {
        $exception = RateLimitException::requestsPerMinute('openai', 100);

        $this->assertEquals(60, $exception->retryAfterSeconds);
    }

    public function testTokensPerMinute(): void
    {
        $exception = RateLimitException::tokensPerMinute('openai', 10000, 45);

        $this->assertStringContainsString('10000 tokens per minute', $exception->getMessage());
        $this->assertEquals(45, $exception->retryAfterSeconds);
    }

    public function testToArray(): void
    {
        $exception = new RateLimitException('Test', 'openai', retryAfterSeconds: 60);

        $array = $exception->toArray();

        $this->assertEquals(60, $array['retryAfterSeconds']);
    }
}
