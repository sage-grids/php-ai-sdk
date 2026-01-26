<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\ModelNotFoundException;
use SageGrids\PhpAiSdk\Exception\ProviderException;
use SageGrids\PhpAiSdk\Exception\ProviderUnavailableException;
use SageGrids\PhpAiSdk\Exception\QuotaExceededException;

final class OtherProviderExceptionsTest extends TestCase
{
    // QuotaExceededException tests

    public function testQuotaExceededExceptionIsProviderException(): void
    {
        $exception = new QuotaExceededException('Quota exceeded', 'openai');

        $this->assertInstanceOf(ProviderException::class, $exception);
    }

    public function testQuotaExceededInsufficientCredits(): void
    {
        $exception = QuotaExceededException::insufficientCredits('openai');

        $this->assertStringContainsString('Insufficient credits', $exception->getMessage());
        $this->assertStringContainsString('openai', $exception->getMessage());
        $this->assertEquals(402, $exception->statusCode);
    }

    public function testQuotaExceededBillingLimitExceeded(): void
    {
        $exception = QuotaExceededException::billingLimitExceeded('anthropic');

        $this->assertStringContainsString('Billing limit exceeded', $exception->getMessage());
        $this->assertEquals(402, $exception->statusCode);
    }

    public function testQuotaExceededPlanLimitExceeded(): void
    {
        $exception = QuotaExceededException::planLimitExceeded('openai', 'Free');

        $this->assertStringContainsString('Plan limit exceeded', $exception->getMessage());
        $this->assertStringContainsString('Free', $exception->getMessage());
    }

    // ModelNotFoundException tests

    public function testModelNotFoundExceptionIsProviderException(): void
    {
        $exception = new ModelNotFoundException('Model not found', 'openai');

        $this->assertInstanceOf(ProviderException::class, $exception);
    }

    public function testModelNotFoundInvalidModel(): void
    {
        $exception = ModelNotFoundException::invalidModel('openai', 'gpt-5');

        $this->assertStringContainsString('gpt-5', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
        $this->assertEquals('gpt-5', $exception->model);
        $this->assertEquals(404, $exception->statusCode);
    }

    public function testModelNotFoundDeprecatedModelWithReplacement(): void
    {
        $exception = ModelNotFoundException::deprecatedModel('openai', 'gpt-3.5-turbo-0301', 'gpt-3.5-turbo');

        $this->assertStringContainsString('deprecated', $exception->getMessage());
        $this->assertStringContainsString('gpt-3.5-turbo', $exception->getMessage());
        $this->assertStringContainsString('instead', $exception->getMessage());
    }

    public function testModelNotFoundDeprecatedModelWithoutReplacement(): void
    {
        $exception = ModelNotFoundException::deprecatedModel('openai', 'old-model');

        $this->assertStringContainsString('deprecated', $exception->getMessage());
        $this->assertStringNotContainsString('instead', $exception->getMessage());
    }

    public function testModelNotFoundNotAvailableInRegion(): void
    {
        $exception = ModelNotFoundException::notAvailableInRegion('openai', 'gpt-4-turbo', 'EU');

        $this->assertStringContainsString('EU', $exception->getMessage());
        $this->assertStringContainsString('not available', $exception->getMessage());
    }

    public function testModelNotFoundAccessRequired(): void
    {
        $exception = ModelNotFoundException::accessRequired('openai', 'gpt-4-vision');

        $this->assertStringContainsString('special access', $exception->getMessage());
        $this->assertStringContainsString('gpt-4-vision', $exception->getMessage());
    }

    // ProviderUnavailableException tests

    public function testProviderUnavailableExceptionIsProviderException(): void
    {
        $exception = new ProviderUnavailableException('Server error', 'openai');

        $this->assertInstanceOf(ProviderException::class, $exception);
    }

    public function testProviderUnavailableInternalError(): void
    {
        $exception = ProviderUnavailableException::internalError('openai', 'gpt-4');

        $this->assertStringContainsString('internal error', $exception->getMessage());
        $this->assertEquals(500, $exception->statusCode);
        $this->assertEquals('gpt-4', $exception->model);
    }

    public function testProviderUnavailableBadGateway(): void
    {
        $exception = ProviderUnavailableException::badGateway('anthropic');

        $this->assertStringContainsString('bad gateway', $exception->getMessage());
        $this->assertEquals(502, $exception->statusCode);
    }

    public function testProviderUnavailableServiceUnavailable(): void
    {
        $exception = ProviderUnavailableException::serviceUnavailable('openai');

        $this->assertStringContainsString('unavailable', $exception->getMessage());
        $this->assertEquals(503, $exception->statusCode);
    }

    public function testProviderUnavailableGatewayTimeout(): void
    {
        $exception = ProviderUnavailableException::gatewayTimeout('openai');

        $this->assertStringContainsString('timed out', $exception->getMessage());
        $this->assertEquals(504, $exception->statusCode);
    }

    public function testProviderUnavailableOverloaded(): void
    {
        $exception = ProviderUnavailableException::overloaded('openai');

        $this->assertStringContainsString('overloaded', $exception->getMessage());
        $this->assertEquals(503, $exception->statusCode);
    }

    public function testProviderUnavailableMaintenanceWithDuration(): void
    {
        $exception = ProviderUnavailableException::maintenance('openai', '2 hours');

        $this->assertStringContainsString('maintenance', $exception->getMessage());
        $this->assertStringContainsString('2 hours', $exception->getMessage());
    }

    public function testProviderUnavailableMaintenanceWithoutDuration(): void
    {
        $exception = ProviderUnavailableException::maintenance('openai');

        $this->assertStringContainsString('maintenance', $exception->getMessage());
        $this->assertStringNotContainsString('duration', $exception->getMessage());
    }
}
