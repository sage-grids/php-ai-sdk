<?php

namespace SageGrids\PhpAiSdk\Provider;

/**
 * Defines the capabilities supported by a provider.
 */
final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $supportsTextGeneration = false,
        public bool $supportsStreaming = false,
        public bool $supportsStructuredOutput = false,
        public bool $supportsToolCalling = false,
        public bool $supportsImageGeneration = false,
        public bool $supportsSpeechGeneration = false,
        public bool $supportsTranscription = false,
        public bool $supportsEmbeddings = false,
        public bool $supportsVision = false,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'supportsTextGeneration' => $this->supportsTextGeneration,
            'supportsStreaming' => $this->supportsStreaming,
            'supportsStructuredOutput' => $this->supportsStructuredOutput,
            'supportsToolCalling' => $this->supportsToolCalling,
            'supportsImageGeneration' => $this->supportsImageGeneration,
            'supportsSpeechGeneration' => $this->supportsSpeechGeneration,
            'supportsTranscription' => $this->supportsTranscription,
            'supportsEmbeddings' => $this->supportsEmbeddings,
            'supportsVision' => $this->supportsVision,
        ];
    }
}
