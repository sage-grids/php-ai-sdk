<?php

namespace Tests\Unit\Provider\OpenAI;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;

final class OpenAIConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new OpenAIConfig();

        $this->assertEquals('https://api.openai.com/v1', $config->baseUrl);
        $this->assertNull($config->organization);
        $this->assertNull($config->project);
        $this->assertEquals(30, $config->timeout);
        $this->assertEquals('gpt-4o', $config->defaultModel);
        $this->assertEquals('text-embedding-3-small', $config->defaultEmbeddingModel);
    }

    public function testCustomValues(): void
    {
        $config = new OpenAIConfig(
            baseUrl: 'https://custom.api.com/v1',
            organization: 'org-123',
            project: 'proj-456',
            timeout: 60,
            defaultModel: 'gpt-4-turbo',
            defaultEmbeddingModel: 'text-embedding-3-large',
        );

        $this->assertEquals('https://custom.api.com/v1', $config->baseUrl);
        $this->assertEquals('org-123', $config->organization);
        $this->assertEquals('proj-456', $config->project);
        $this->assertEquals(60, $config->timeout);
        $this->assertEquals('gpt-4-turbo', $config->defaultModel);
        $this->assertEquals('text-embedding-3-large', $config->defaultEmbeddingModel);
    }

    public function testDefaultBaseUrlConstant(): void
    {
        $this->assertEquals('https://api.openai.com/v1', OpenAIConfig::DEFAULT_BASE_URL);
    }
}
