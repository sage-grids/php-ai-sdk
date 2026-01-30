<?php

namespace Tests\Unit\Provider\OpenRouter;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\InsufficientCreditsException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\InvalidRequestException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\NotFoundException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\OpenRouterException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\RateLimitException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\ServerException;
use SageGrids\PhpAiSdk\Provider\OpenRouter\Exception\TimeoutException;

final class ExceptionTest extends TestCase
{
    public function testOpenRouterExceptionConstructor(): void
    {
        $errorData = ['key' => 'value'];
        $exception = new OpenRouterException('Test message', 400, $errorData);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
        $this->assertEquals($errorData, $exception->errorData);
    }

    public function testFromResponseCreatesAuthenticationException(): void
    {
        $response = ['error' => ['message' => 'Invalid API key']];
        $exception = OpenRouterException::fromApiResponse(401, $response);

        $this->assertInstanceOf(AuthenticationException::class, $exception);
        $this->assertEquals('Invalid API key', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
    }

    public function testFromResponseCreatesRateLimitException(): void
    {
        $response = ['error' => ['message' => 'Rate limit exceeded']];
        $exception = OpenRouterException::fromApiResponse(429, $response);

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertEquals('Rate limit exceeded', $exception->getMessage());
    }

    public function testFromResponseCreatesInvalidRequestException(): void
    {
        $response = ['error' => ['message' => 'Invalid request']];
        $exception = OpenRouterException::fromApiResponse(400, $response);

        $this->assertInstanceOf(InvalidRequestException::class, $exception);
        $this->assertEquals('Invalid request', $exception->getMessage());
    }

    public function testFromResponseCreatesInsufficientCreditsException(): void
    {
        $response = ['error' => ['message' => 'Insufficient credits']];
        $exception = OpenRouterException::fromApiResponse(402, $response);

        $this->assertInstanceOf(InsufficientCreditsException::class, $exception);
        $this->assertEquals('Insufficient credits', $exception->getMessage());
    }

    public function testFromResponseCreatesNotFoundException(): void
    {
        $response = ['error' => ['message' => 'Model not found']];
        $exception = OpenRouterException::fromApiResponse(404, $response);

        $this->assertInstanceOf(NotFoundException::class, $exception);
        $this->assertEquals('Model not found', $exception->getMessage());
    }

    public function testFromResponseCreatesTimeoutException(): void
    {
        $response = ['error' => ['message' => 'Request timed out']];
        $exception = OpenRouterException::fromApiResponse(408, $response);

        $this->assertInstanceOf(TimeoutException::class, $exception);
        $this->assertEquals('Request timed out', $exception->getMessage());
    }

    public function testFromResponseCreatesServerException(): void
    {
        $statusCodes = [500, 502, 503, 504];

        foreach ($statusCodes as $statusCode) {
            $response = ['error' => ['message' => 'Server error']];
            $exception = OpenRouterException::fromApiResponse($statusCode, $response);

            $this->assertInstanceOf(ServerException::class, $exception);
            $this->assertEquals('Server error', $exception->getMessage());
            $this->assertEquals($statusCode, $exception->getCode());
        }
    }

    public function testFromResponseCreatesGenericExceptionForUnknownStatus(): void
    {
        $response = ['error' => ['message' => 'Unknown error']];
        $exception = OpenRouterException::fromApiResponse(418, $response);

        $this->assertInstanceOf(OpenRouterException::class, $exception);
        $this->assertNotInstanceOf(AuthenticationException::class, $exception);
        $this->assertNotInstanceOf(RateLimitException::class, $exception);
        $this->assertEquals('Unknown error', $exception->getMessage());
    }

    public function testFromResponseUsesDefaultMessageWhenMissing(): void
    {
        $response = [];
        $exception = OpenRouterException::fromApiResponse(500, $response);

        $this->assertEquals('Unknown OpenRouter API error', $exception->getMessage());
    }
}
