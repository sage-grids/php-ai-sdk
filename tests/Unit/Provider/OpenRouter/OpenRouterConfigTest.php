<?php

namespace Tests\Unit\Provider\OpenRouter;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\OpenRouter\OpenRouterConfig;

final class OpenRouterConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new OpenRouterConfig();

        $this->assertEquals('https://openrouter.ai/api/v1', $config->baseUrl);
        $this->assertNull($config->siteUrl);
        $this->assertNull($config->appName);
        $this->assertEquals(30, $config->timeout);
        $this->assertEquals('anthropic/claude-3.5-sonnet', $config->defaultModel);
    }

    public function testCustomValues(): void
    {
        $config = new OpenRouterConfig(
            baseUrl: 'https://custom.openrouter.ai/api/v1',
            siteUrl: 'https://mysite.com',
            appName: 'My App',
            timeout: 60,
            defaultModel: 'openai/gpt-4o',
        );

        $this->assertEquals('https://custom.openrouter.ai/api/v1', $config->baseUrl);
        $this->assertEquals('https://mysite.com', $config->siteUrl);
        $this->assertEquals('My App', $config->appName);
        $this->assertEquals(60, $config->timeout);
        $this->assertEquals('openai/gpt-4o', $config->defaultModel);
    }

    public function testDefaultBaseUrlConstant(): void
    {
        $this->assertEquals('https://openrouter.ai/api/v1', OpenRouterConfig::DEFAULT_BASE_URL);
    }
}
