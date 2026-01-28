<?php

namespace Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\AIConfig;
use SageGrids\PhpAiSdk\Event\EventDispatcherInterface;
use SageGrids\PhpAiSdk\Event\NullEventDispatcher;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;
use SageGrids\PhpAiSdk\Provider\TextProviderInterface;

final class AIConfigTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        AIConfig::reset();
        ProviderRegistry::resetInstance();
    }

    protected function tearDown(): void
    {
        AIConfig::reset();
        ProviderRegistry::resetInstance();
    }

    public function testSetAndGetProvider(): void
    {
        $this->assertNull(AIConfig::getProvider());

        AIConfig::setProvider('openai/gpt-4o');
        $this->assertEquals('openai/gpt-4o', AIConfig::getProvider());

        // Test with provider instance (using Mockery for final classes)
        $provider = Mockery::mock(TextProviderInterface::class);
        AIConfig::setProvider($provider);
        $this->assertSame($provider, AIConfig::getProvider());
    }

    public function testSetAndGetDefaults(): void
    {
        $this->assertEquals([], AIConfig::getDefaults());

        $defaults = [
            'temperature' => 0.7,
            'maxTokens' => 1000,
        ];

        AIConfig::setDefaults($defaults);
        $this->assertEquals($defaults, AIConfig::getDefaults());
    }

    public function testMergeWithDefaults(): void
    {
        AIConfig::setDefaults([
            'temperature' => 0.7,
            'maxTokens' => 1000,
            'topP' => 0.9,
        ]);

        $options = [
            'temperature' => 0.5, // Override default
            'prompt' => 'Hello',   // New option
        ];

        $merged = AIConfig::mergeWithDefaults($options);

        $this->assertEquals(0.5, $merged['temperature']); // Overridden
        $this->assertEquals(1000, $merged['maxTokens']); // From defaults
        $this->assertEquals(0.9, $merged['topP']);        // From defaults
        $this->assertEquals('Hello', $merged['prompt']); // New option
    }

    public function testSetAndGetTimeout(): void
    {
        $this->assertEquals(30, AIConfig::getTimeout()); // Default

        AIConfig::setTimeout(60);
        $this->assertEquals(60, AIConfig::getTimeout());
    }

    public function testSetAndGetMaxToolRoundtrips(): void
    {
        $this->assertEquals(5, AIConfig::getMaxToolRoundtrips()); // Default

        AIConfig::setMaxToolRoundtrips(10);
        $this->assertEquals(10, AIConfig::getMaxToolRoundtrips());
    }

    public function testReset(): void
    {
        AIConfig::setProvider('openai/gpt-4o');
        AIConfig::setDefaults(['temperature' => 0.7]);
        AIConfig::setTimeout(60);
        AIConfig::setMaxToolRoundtrips(10);

        // Set a custom event dispatcher
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        AIConfig::setEventDispatcher($dispatcher);

        AIConfig::reset();

        $this->assertNull(AIConfig::getProvider());
        $this->assertEquals([], AIConfig::getDefaults());
        $this->assertEquals(30, AIConfig::getTimeout());
        $this->assertEquals(5, AIConfig::getMaxToolRoundtrips());
        // After reset, should return NullEventDispatcher
        $this->assertInstanceOf(NullEventDispatcher::class, AIConfig::getEventDispatcher());
    }

    public function testGetEventDispatcherReturnsNullEventDispatcherByDefault(): void
    {
        $dispatcher = AIConfig::getEventDispatcher();

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $this->assertInstanceOf(NullEventDispatcher::class, $dispatcher);
    }

    public function testSetAndGetEventDispatcher(): void
    {
        $customDispatcher = Mockery::mock(EventDispatcherInterface::class);

        AIConfig::setEventDispatcher($customDispatcher);

        $this->assertSame($customDispatcher, AIConfig::getEventDispatcher());
    }

    public function testEventDispatcherIsResetOnReset(): void
    {
        $customDispatcher = Mockery::mock(EventDispatcherInterface::class);
        AIConfig::setEventDispatcher($customDispatcher);

        $this->assertSame($customDispatcher, AIConfig::getEventDispatcher());

        AIConfig::reset();

        $this->assertInstanceOf(NullEventDispatcher::class, AIConfig::getEventDispatcher());
    }
}
