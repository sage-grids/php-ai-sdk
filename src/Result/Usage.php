<?php

namespace SageGrids\PhpAiSdk\Result;

/**
 * Token usage statistics from an API call.
 *
 * This is a stub class. Full implementation will be added in the result classes task.
 */
final readonly class Usage
{
    /**
     * @param int $promptTokens Number of tokens in the prompt.
     * @param int $completionTokens Number of tokens in the completion.
     * @param int $totalTokens Total tokens used (prompt + completion).
     */
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
    ) {
    }
}
