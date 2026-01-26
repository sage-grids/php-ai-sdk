<?php

namespace Tests\Unit\Provider\OpenAI;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\InvalidRequestException;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\NotFoundException;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\OpenAIException;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\RateLimitException;
use SageGrids\PhpAiSdk\Provider\OpenAI\Exception\ServerException;

final class ExceptionTest extends TestCase
{
    public function testOpenAIExceptionFromResponse401(): void
    {
        $response = ['error' => ['message' => 'Invalid API key', 'type' => 'invalid_api_key']];

        $exception = OpenAIException::fromResponse(401, $response);

        $this->assertInstanceOf(AuthenticationException::class, $exception);
        $this->assertEquals('Invalid API key', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals($response, $exception->errorData);
    }

    public function testOpenAIExceptionFromResponse429(): void
    {
        $response = ['error' => ['message' => 'Rate limit exceeded', 'type' => 'rate_limit_error']];

        $exception = OpenAIException::fromResponse(429, $response);

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertEquals('Rate limit exceeded', $exception->getMessage());
        $this->assertEquals(429, $exception->getCode());
    }

    public function testOpenAIExceptionFromResponse400(): void
    {
        $response = ['error' => ['message' => 'Invalid request', 'type' => 'invalid_request_error']];

        $exception = OpenAIException::fromResponse(400, $response);

        $this->assertInstanceOf(InvalidRequestException::class, $exception);
        $this->assertEquals('Invalid request', $exception->getMessage());
    }

    public function testOpenAIExceptionFromResponse404(): void
    {
        $response = ['error' => ['message' => 'Model not found', 'type' => 'not_found_error']];

        $exception = OpenAIException::fromResponse(404, $response);

        $this->assertInstanceOf(NotFoundException::class, $exception);
        $this->assertEquals('Model not found', $exception->getMessage());
    }

    public function testOpenAIExceptionFromResponse500(): void
    {
        $response = ['error' => ['message' => 'Internal server error', 'type' => 'server_error']];

        $exception = OpenAIException::fromResponse(500, $response);

        $this->assertInstanceOf(ServerException::class, $exception);
        $this->assertEquals('Internal server error', $exception->getMessage());
    }

    public function testOpenAIExceptionFromResponse502(): void
    {
        $response = ['error' => ['message' => 'Bad gateway']];

        $exception = OpenAIException::fromResponse(502, $response);

        $this->assertInstanceOf(ServerException::class, $exception);
    }

    public function testOpenAIExceptionFromResponse503(): void
    {
        $response = ['error' => ['message' => 'Service unavailable']];

        $exception = OpenAIException::fromResponse(503, $response);

        $this->assertInstanceOf(ServerException::class, $exception);
    }

    public function testOpenAIExceptionFromResponse504(): void
    {
        $response = ['error' => ['message' => 'Gateway timeout']];

        $exception = OpenAIException::fromResponse(504, $response);

        $this->assertInstanceOf(ServerException::class, $exception);
    }

    public function testOpenAIExceptionFromResponseUnknownStatusCode(): void
    {
        $response = ['error' => ['message' => 'Unknown error']];

        $exception = OpenAIException::fromResponse(418, $response);

        $this->assertInstanceOf(OpenAIException::class, $exception);
        $this->assertNotInstanceOf(AuthenticationException::class, $exception);
        $this->assertEquals('Unknown error', $exception->getMessage());
    }

    public function testOpenAIExceptionFromResponseWithMissingMessage(): void
    {
        $response = ['error' => []];

        $exception = OpenAIException::fromResponse(500, $response);

        $this->assertEquals('Unknown OpenAI API error', $exception->getMessage());
    }

    public function testOpenAIExceptionFromResponseWithMissingError(): void
    {
        $response = [];

        $exception = OpenAIException::fromResponse(500, $response);

        $this->assertEquals('Unknown OpenAI API error', $exception->getMessage());
    }

    public function testRateLimitExceptionGetRetryAfter(): void
    {
        $response = ['error' => ['message' => 'Rate limited'], 'retry_after' => 30];

        $exception = new RateLimitException('Rate limited', 429, $response);

        $this->assertEquals(30, $exception->getRetryAfter());
    }

    public function testRateLimitExceptionGetRetryAfterReturnsNullWhenNotSet(): void
    {
        $response = ['error' => ['message' => 'Rate limited']];

        $exception = new RateLimitException('Rate limited', 429, $response);

        $this->assertNull($exception->getRetryAfter());
    }

    public function testOpenAIExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new OpenAIException('Current error', 500, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
