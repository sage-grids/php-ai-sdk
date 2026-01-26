<?php

namespace Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Provider\ProviderCapabilities;

final class ProviderCapabilitiesTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $capabilities = new ProviderCapabilities();

        $this->assertFalse($capabilities->supportsTextGeneration);
        $this->assertFalse($capabilities->supportsStreaming);
        $this->assertFalse($capabilities->supportsStructuredOutput);
        $this->assertFalse($capabilities->supportsToolCalling);
        $this->assertFalse($capabilities->supportsImageGeneration);
        $this->assertFalse($capabilities->supportsSpeechGeneration);
        $this->assertFalse($capabilities->supportsTranscription);
        $this->assertFalse($capabilities->supportsEmbeddings);
        $this->assertFalse($capabilities->supportsVision);
    }

    public function testCustomValues(): void
    {
        $capabilities = new ProviderCapabilities(
            supportsTextGeneration: true,
            supportsStreaming: true,
            supportsStructuredOutput: true,
            supportsToolCalling: true,
        );

        $this->assertTrue($capabilities->supportsTextGeneration);
        $this->assertTrue($capabilities->supportsStreaming);
        $this->assertTrue($capabilities->supportsStructuredOutput);
        $this->assertTrue($capabilities->supportsToolCalling);
        $this->assertFalse($capabilities->supportsImageGeneration);
        $this->assertFalse($capabilities->supportsSpeechGeneration);
    }

    public function testToArray(): void
    {
        $capabilities = new ProviderCapabilities(
            supportsTextGeneration: true,
            supportsVision: true,
        );

        $array = $capabilities->toArray();

        $this->assertTrue($array['supportsTextGeneration']);
        $this->assertTrue($array['supportsVision']);
        $this->assertFalse($array['supportsStreaming']);
        $this->assertCount(9, $array);
    }
}
