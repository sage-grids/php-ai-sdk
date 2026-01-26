<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Exception\ModelNotFoundException;
use SageGrids\PhpAiSdk\Exception\ProviderException;
use SageGrids\PhpAiSdk\Exception\ProviderUnavailableException;
use SageGrids\PhpAiSdk\Exception\QuotaExceededException;
use SageGrids\PhpAiSdk\Exception\RateLimitException;

final class ProviderExceptionTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $errorDetails = ['type' => 'test_error'];
        $previous = new RuntimeException('Previous');

        $exception = new ProviderException(
            'Test error',
            'openai',
            'gpt-4',
            500,
            $errorDetails,
            'req_123',
            100,
            $previous,
        );

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals('openai', $exception->provider);
        $this->assertEquals('gpt-4', $exception->model);
        $this->assertEquals(500, $exception->statusCode);
        $this->assertEquals($errorDetails, $exception->errorDetails);
        $this->assertEquals('req_123', $exception->requestId);
        $this->assertEquals(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFromResponse401(): void
    {
        $response = ['error' => ['message' => 'Invalid API key']];

        $exception = ProviderException::fromResponse('openai', 401, $response, 'gpt-4', 'req_123');

        $this->assertInstanceOf(AuthenticationException::class, $exception);
        $this->assertEquals('Invalid API key', $exception->getMessage());
        $this->assertEquals('openai', $exception->provider);
        $this->assertEquals('gpt-4', $exception->model);
        $this->assertEquals(401, $exception->statusCode);
        $this->assertEquals('req_123', $exception->requestId);
    }

    public function testFromResponse429(): void
    {
        $response = ['error' => ['message' => 'Rate limit exceeded'], 'retry_after' => 60];

        $exception = ProviderException::fromResponse('openai', 429, $response);

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertEquals('Rate limit exceeded', $exception->getMessage());
        $this->assertEquals(60, $exception->retryAfterSeconds);
    }

    public function testFromResponse402(): void
    {
        $response = ['error' => ['message' => 'Insufficient credits']];

        $exception = ProviderException::fromResponse('openai', 402, $response);

        $this->assertInstanceOf(QuotaExceededException::class, $exception);
        $this->assertEquals('Insufficient credits', $exception->getMessage());
    }

    public function testFromResponse404(): void
    {
        $response = ['error' => ['message' => 'Model not found']];

        $exception = ProviderException::fromResponse('openai', 404, $response, 'gpt-5');

        $this->assertInstanceOf(ModelNotFoundException::class, $exception);
        $this->assertEquals('gpt-5', $exception->model);
    }

    /**
     * @dataProvider serverErrorStatusCodesProvider
     */
    public function testFromResponseServerErrors(int $statusCode): void
    {
        $response = ['error' => ['message' => 'Server error']];

        $exception = ProviderException::fromResponse('openai', $statusCode, $response);

        $this->assertInstanceOf(ProviderUnavailableException::class, $exception);
        $this->assertEquals($statusCode, $exception->statusCode);
    }

    /**
     * @return array<array{int}>
     */
    public static function serverErrorStatusCodesProvider(): array
    {
        return [[500], [502], [503], [504]];
    }

    public function testFromResponseUnknownStatusCode(): void
    {
        $response = ['error' => ['message' => 'Unknown error']];

        $exception = ProviderException::fromResponse('openai', 418, $response);

        $this->assertInstanceOf(ProviderException::class, $exception);
        $this->assertNotInstanceOf(AuthenticationException::class, $exception);
        $this->assertEquals(418, $exception->statusCode);
    }

    public function testFromResponseExtractsMessageFromVariousFormats(): void
    {
        // error.message format
        $exception1 = ProviderException::fromResponse('test', 400, ['error' => ['message' => 'Error 1']]);
        $this->assertEquals('Error 1', $exception1->getMessage());

        // message format
        $exception2 = ProviderException::fromResponse('test', 400, ['message' => 'Error 2']);
        $this->assertEquals('Error 2', $exception2->getMessage());

        // error as string
        $exception3 = ProviderException::fromResponse('test', 400, ['error' => 'Error 3']);
        $this->assertEquals('Error 3', $exception3->getMessage());

        // unknown format
        $exception4 = ProviderException::fromResponse('test', 400, []);
        $this->assertEquals('Unknown provider error', $exception4->getMessage());
    }

    public function testForProvider(): void
    {
        $previous = new RuntimeException('Original');

        $exception = ProviderException::forProvider('anthropic', 'Something went wrong', 'claude-3', $previous);

        $this->assertEquals('Something went wrong', $exception->getMessage());
        $this->assertEquals('anthropic', $exception->provider);
        $this->assertEquals('claude-3', $exception->model);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testIsRetryable(): void
    {
        $retryable = [429, 500, 502, 503, 504];
        $nonRetryable = [400, 401, 402, 403, 404];

        foreach ($retryable as $code) {
            $exception = new ProviderException('Test', 'test', statusCode: $code);
            $this->assertTrue($exception->isRetryable(), "Status $code should be retryable");
        }

        foreach ($nonRetryable as $code) {
            $exception = new ProviderException('Test', 'test', statusCode: $code);
            $this->assertFalse($exception->isRetryable(), "Status $code should not be retryable");
        }
    }

    public function testToArray(): void
    {
        $exception = new ProviderException(
            'Test error',
            'openai',
            'gpt-4',
            500,
            ['type' => 'error'],
            'req_123',
        );

        $array = $exception->toArray();

        $this->assertEquals('openai', $array['provider']);
        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(500, $array['statusCode']);
        $this->assertEquals(['type' => 'error'], $array['errorDetails']);
        $this->assertEquals('req_123', $array['requestId']);
        $this->assertTrue($array['retryable']);
    }
}
