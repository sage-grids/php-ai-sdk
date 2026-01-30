<?php

namespace Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\AIContext;
use SageGrids\PhpAiSdk\Event\EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\NullEventDispatcher;
use SageGrids\PhpAiSdk\Provider\ProviderInterface;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Provider\Exception\ProviderNotFoundException;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;

final class AIContextTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testConstructorCreatesIsolatedRegistry(): void
    {
        $context1 = new AIContext();
        $context2 = new AIContext();

        // Each context should have its own registry instance
        $this->assertNotSame($context1->registry(), $context2->registry());
    }

    public function testRegistryIsIsolatedFromSingleton(): void
    {
        $globalRegistry = ProviderRegistry::getInstance();
        $context = new AIContext();

        // Register provider in global registry
        $provider = Mockery::mock(ProviderInterface::class);
        $globalRegistry->register('test-provider', $provider);

        // Context registry should not have this provider
        $this->assertTrue($globalRegistry->has('test-provider'));
        $this->assertFalse($context->registry()->has('test-provider'));

        // Cleanup
        $globalRegistry->unregister('test-provider');
    }

    public function testSetAndGetProvider(): void
    {
        $context = new AIContext();

        $this->assertNull($context->getProvider());

        $context->setProvider('openai/gpt-4o');
        $this->assertEquals('openai/gpt-4o', $context->getProvider());

        // Test with provider instance
        $provider = Mockery::mock(TextProviderInterface::class);
        $context->setProvider($provider);
        $this->assertSame($provider, $context->getProvider());
    }

    public function testSetProviderReturnsSelf(): void
    {
        $context = new AIContext();
        $result = $context->setProvider('openai/gpt-4o');
        $this->assertSame($context, $result);
    }

    public function testSetAndGetDefaults(): void
    {
        $context = new AIContext();

        $this->assertEquals([], $context->getDefaults());

        $defaults = [
            'temperature' => 0.7,
            'maxTokens' => 1000,
        ];

        $context->setDefaults($defaults);
        $this->assertEquals($defaults, $context->getDefaults());
    }

    public function testSetDefaultsReturnsSelf(): void
    {
        $context = new AIContext();
        $result = $context->setDefaults(['temperature' => 0.5]);
        $this->assertSame($context, $result);
    }

    public function testMergeWithDefaults(): void
    {
        $context = new AIContext();
        $context->setDefaults([
            'temperature' => 0.7,
            'maxTokens' => 1000,
            'topP' => 0.9,
        ]);

        $options = [
            'temperature' => 0.5, // Override default
            'prompt' => 'Hello',   // New option
        ];

        $merged = $context->mergeWithDefaults($options);

        $this->assertEquals(0.5, $merged['temperature']); // Overridden
        $this->assertEquals(1000, $merged['maxTokens']); // From defaults
        $this->assertEquals(0.9, $merged['topP']);        // From defaults
        $this->assertEquals('Hello', $merged['prompt']); // New option
    }

    public function testSetAndGetTimeout(): void
    {
        $context = new AIContext();

        $this->assertEquals(30, $context->getTimeout()); // Default

        $context->setTimeout(60);
        $this->assertEquals(60, $context->getTimeout());
    }

    public function testSetTimeoutReturnsSelf(): void
    {
        $context = new AIContext();
        $result = $context->setTimeout(60);
        $this->assertSame($context, $result);
    }

    public function testSetAndGetMaxToolRoundtrips(): void
    {
        $context = new AIContext();

        $this->assertEquals(5, $context->getMaxToolRoundtrips()); // Default

        $context->setMaxToolRoundtrips(10);
        $this->assertEquals(10, $context->getMaxToolRoundtrips());
    }

    public function testSetMaxToolRoundtripsReturnsSelf(): void
    {
        $context = new AIContext();
        $result = $context->setMaxToolRoundtrips(10);
        $this->assertSame($context, $result);
    }

    public function testGetEventDispatcherReturnsNullEventDispatcherByDefault(): void
    {
        $context = new AIContext();
        $dispatcher = $context->getEventDispatcher();

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $this->assertInstanceOf(NullEventDispatcher::class, $dispatcher);
    }

    public function testSetAndGetEventDispatcher(): void
    {
        $context = new AIContext();
        $customDispatcher = Mockery::mock(EventDispatcherInterface::class);

        $context->setEventDispatcher($customDispatcher);

        $this->assertSame($customDispatcher, $context->getEventDispatcher());
    }

    public function testSetEventDispatcherReturnsSelf(): void
    {
        $context = new AIContext();
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $result = $context->setEventDispatcher($dispatcher);
        $this->assertSame($context, $result);
    }

    public function testReset(): void
    {
        $context = new AIContext();

        // Set various configuration
        $context->setProvider('openai/gpt-4o');
        $context->setDefaults(['temperature' => 0.7]);
        $context->setTimeout(60);
        $context->setMaxToolRoundtrips(10);

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $context->setEventDispatcher($dispatcher);

        // Register a provider
        $provider = Mockery::mock(ProviderInterface::class);
        $context->registry()->register('test', $provider);

        // Reset
        $context->reset();

        $this->assertNull($context->getProvider());
        $this->assertEquals([], $context->getDefaults());
        $this->assertEquals(30, $context->getTimeout());
        $this->assertEquals(5, $context->getMaxToolRoundtrips());
        $this->assertInstanceOf(NullEventDispatcher::class, $context->getEventDispatcher());
        $this->assertFalse($context->registry()->has('test'));
    }

    public function testResetReturnsSelf(): void
    {
        $context = new AIContext();
        $result = $context->reset();
        $this->assertSame($context, $result);
    }

    public function testProviderMethodReturnsRegisteredProvider(): void
    {
        $context = new AIContext();
        $provider = Mockery::mock(ProviderInterface::class);

        $context->registry()->register('openai', $provider);

        $this->assertSame($provider, $context->provider('openai'));
    }

    public function testProviderMethodThrowsExceptionForUnregisteredProvider(): void
    {
        $context = new AIContext();

        $this->expectException(ProviderNotFoundException::class);
        $context->provider('nonexistent');
    }

    public function testMultipleContextsAreIsolated(): void
    {
        $context1 = new AIContext();
        $context2 = new AIContext();

        // Configure context1
        $context1->setProvider('openai/gpt-4o');
        $context1->setTimeout(60);
        $provider1 = Mockery::mock(ProviderInterface::class);
        $context1->registry()->register('openai', $provider1);

        // Configure context2 differently
        $context2->setProvider('anthropic/claude-3');
        $context2->setTimeout(30);
        $provider2 = Mockery::mock(ProviderInterface::class);
        $context2->registry()->register('anthropic', $provider2);

        // Verify isolation
        $this->assertEquals('openai/gpt-4o', $context1->getProvider());
        $this->assertEquals('anthropic/claude-3', $context2->getProvider());

        $this->assertEquals(60, $context1->getTimeout());
        $this->assertEquals(30, $context2->getTimeout());

        $this->assertTrue($context1->registry()->has('openai'));
        $this->assertFalse($context1->registry()->has('anthropic'));

        $this->assertTrue($context2->registry()->has('anthropic'));
        $this->assertFalse($context2->registry()->has('openai'));
    }

    public function testFluentInterface(): void
    {
        $context = new AIContext();

        $result = $context
            ->setProvider('openai/gpt-4o')
            ->setDefaults(['temperature' => 0.7])
            ->setTimeout(60)
            ->setMaxToolRoundtrips(10);

        $this->assertSame($context, $result);
        $this->assertEquals('openai/gpt-4o', $context->getProvider());
        $this->assertEquals(['temperature' => 0.7], $context->getDefaults());
        $this->assertEquals(60, $context->getTimeout());
        $this->assertEquals(10, $context->getMaxToolRoundtrips());
    }
}
