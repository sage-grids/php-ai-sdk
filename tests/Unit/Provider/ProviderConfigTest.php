<?php

namespace Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\ProviderConfig;

final class ProviderConfigTest extends TestCase
{
    public function testRequiredApiKey(): void
    {
        $config = new ProviderConfig(apiKey: 'sk-test-key');

        $this->assertEquals('sk-test-key', $config->apiKey);
    }

    public function testDefaultValues(): void
    {
        $config = new ProviderConfig(apiKey: 'sk-test-key');

        $this->assertNull($config->baseUrl);
        $this->assertNull($config->organization);
        $this->assertEquals(30, $config->timeout);
        $this->assertEquals(3, $config->maxRetries);
        $this->assertEmpty($config->headers);
        $this->assertEmpty($config->options);
    }

    public function testCustomValues(): void
    {
        $config = new ProviderConfig(
            apiKey: 'sk-test-key',
            baseUrl: 'https://api.custom.com/v1',
            organization: 'org-12345',
            timeout: 60,
            maxRetries: 5,
            headers: ['X-Custom-Header' => 'value'],
            options: ['custom_option' => true],
        );

        $this->assertEquals('sk-test-key', $config->apiKey);
        $this->assertEquals('https://api.custom.com/v1', $config->baseUrl);
        $this->assertEquals('org-12345', $config->organization);
        $this->assertEquals(60, $config->timeout);
        $this->assertEquals(5, $config->maxRetries);
        $this->assertEquals(['X-Custom-Header' => 'value'], $config->headers);
        $this->assertEquals(['custom_option' => true], $config->options);
    }
}
