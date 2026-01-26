<?php

namespace Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\Exception\InvalidModelStringException;
use SageGrids\PhpAiSdk\Provider\Exception\ProviderNotFoundException;
use SageGrids\PhpAiSdk\Provider\ProviderCapabilities;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;

final class ProviderRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        ProviderRegistry::resetInstance();
    }

    protected function tearDown(): void
    {
        ProviderRegistry::resetInstance();
    }

    public function testSingletonInstance(): void
    {
        $registry1 = ProviderRegistry::getInstance();
        $registry2 = ProviderRegistry::getInstance();

        $this->assertSame($registry1, $registry2);
    }

    public function testRegisterAndGet(): void
    {
        $registry = ProviderRegistry::getInstance();
        $provider = $this->createMockProvider('openai');

        $registry->register('openai', $provider);

        $this->assertSame($provider, $registry->get('openai'));
    }

    public function testHas(): void
    {
        $registry = ProviderRegistry::getInstance();
        $provider = $this->createMockProvider('anthropic');

        $this->assertFalse($registry->has('anthropic'));

        $registry->register('anthropic', $provider);

        $this->assertTrue($registry->has('anthropic'));
    }

    public function testGetThrowsExceptionForUnknownProvider(): void
    {
        $registry = ProviderRegistry::getInstance();

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage("Provider 'unknown' not found");

        $registry->get('unknown');
    }

    public function testResolveFromModelString(): void
    {
        $registry = ProviderRegistry::getInstance();
        $provider = $this->createMockProvider('openai');

        $registry->register('openai', $provider);

        $resolved = $registry->resolve('openai/gpt-4o');

        $this->assertSame($provider, $resolved);
    }

    public function testResolveWithComplexModelName(): void
    {
        $registry = ProviderRegistry::getInstance();
        $provider = $this->createMockProvider('anthropic');

        $registry->register('anthropic', $provider);

        $resolved = $registry->resolve('anthropic/claude-3-opus-20240229');

        $this->assertSame($provider, $resolved);
    }

    public function testResolveThrowsExceptionForInvalidModelString(): void
    {
        $registry = ProviderRegistry::getInstance();

        $this->expectException(InvalidModelStringException::class);

        $registry->resolve('invalid-model-string');
    }

    public function testResolveThrowsExceptionForEmptyProvider(): void
    {
        $registry = ProviderRegistry::getInstance();

        $this->expectException(InvalidModelStringException::class);

        $registry->resolve('/gpt-4o');
    }

    public function testResolveThrowsExceptionForEmptyModel(): void
    {
        $registry = ProviderRegistry::getInstance();

        $this->expectException(InvalidModelStringException::class);

        $registry->resolve('openai/');
    }

    public function testParseModelString(): void
    {
        $result = ProviderRegistry::parseModelString('openai/gpt-4o');

        $this->assertEquals('openai', $result['provider']);
        $this->assertEquals('gpt-4o', $result['model']);
    }

    public function testParseModelStringWithSlashInModelName(): void
    {
        $result = ProviderRegistry::parseModelString('openrouter/anthropic/claude-3-opus');

        $this->assertEquals('openrouter', $result['provider']);
        $this->assertEquals('anthropic/claude-3-opus', $result['model']);
    }

    public function testGetRegisteredProviders(): void
    {
        $registry = ProviderRegistry::getInstance();
        $registry->register('openai', $this->createMockProvider('openai'));
        $registry->register('anthropic', $this->createMockProvider('anthropic'));

        $providers = $registry->getRegisteredProviders();

        $this->assertCount(2, $providers);
        $this->assertContains('openai', $providers);
        $this->assertContains('anthropic', $providers);
    }

    public function testUnregister(): void
    {
        $registry = ProviderRegistry::getInstance();
        $registry->register('openai', $this->createMockProvider('openai'));

        $this->assertTrue($registry->has('openai'));

        $registry->unregister('openai');

        $this->assertFalse($registry->has('openai'));
    }

    public function testClear(): void
    {
        $registry = ProviderRegistry::getInstance();
        $registry->register('openai', $this->createMockProvider('openai'));
        $registry->register('anthropic', $this->createMockProvider('anthropic'));

        $registry->clear();

        $this->assertEmpty($registry->getRegisteredProviders());
    }

    private function createMockProvider(string $name): ProviderInterface
    {
        $mock = $this->createMock(ProviderInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('getCapabilities')->willReturn(new ProviderCapabilities());
        $mock->method('getAvailableModels')->willReturn([]);

        return $mock;
    }
}
