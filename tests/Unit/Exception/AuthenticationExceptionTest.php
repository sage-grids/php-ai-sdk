<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\AuthenticationException;
use SageGrids\PhpAiSdk\Exception\ProviderException;

final class AuthenticationExceptionTest extends TestCase
{
    public function testIsProviderException(): void
    {
        $exception = new AuthenticationException('Auth failed', 'openai');

        $this->assertInstanceOf(ProviderException::class, $exception);
    }

    public function testInvalidApiKey(): void
    {
        $exception = AuthenticationException::invalidApiKey('openai');

        $this->assertStringContainsString('Invalid API key', $exception->getMessage());
        $this->assertStringContainsString('openai', $exception->getMessage());
        $this->assertEquals('openai', $exception->provider);
        $this->assertEquals(401, $exception->statusCode);
    }

    public function testMissingApiKey(): void
    {
        $exception = AuthenticationException::missingApiKey('anthropic');

        $this->assertStringContainsString('API key not configured', $exception->getMessage());
        $this->assertStringContainsString('anthropic', $exception->getMessage());
        $this->assertEquals('anthropic', $exception->provider);
        $this->assertEquals(401, $exception->statusCode);
    }

    public function testExpiredApiKey(): void
    {
        $exception = AuthenticationException::expiredApiKey('openai');

        $this->assertStringContainsString('expired', $exception->getMessage());
        $this->assertEquals('openai', $exception->provider);
        $this->assertEquals(401, $exception->statusCode);
    }

    public function testInsufficientPermissionsWithModel(): void
    {
        $exception = AuthenticationException::insufficientPermissions('openai', 'gpt-4-vision');

        $this->assertStringContainsString('Insufficient permissions', $exception->getMessage());
        $this->assertStringContainsString('gpt-4-vision', $exception->getMessage());
        $this->assertEquals('gpt-4-vision', $exception->model);
    }

    public function testInsufficientPermissionsWithoutModel(): void
    {
        $exception = AuthenticationException::insufficientPermissions('openai');

        $this->assertStringContainsString('Insufficient permissions', $exception->getMessage());
        $this->assertNull($exception->model);
    }
}
