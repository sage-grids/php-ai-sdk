<?php

namespace SageGrids\PhpAiSdk\Provider;

use Generator;
use SageGrids\PhpAiSdk\Core\Message\Message;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Core\Tool\Tool;

/**
 * Interface for providers that support text generation.
 */
interface TextProviderInterface extends ProviderInterface
{
    /**
     * Generate text from messages.
     *
     * @param Message[] $messages The conversation messages.
     * @param string|null $system Optional system message.
     * @param int|null $maxTokens Maximum tokens to generate.
     * @param float|null $temperature Sampling temperature (0-2).
     * @param float|null $topP Top-p sampling parameter.
     * @param string[]|null $stopSequences Sequences that stop generation.
     * @param Tool[]|null $tools Available tools for the model to use.
     * @param string|Tool|null $toolChoice Control tool usage: 'auto', 'none', 'required', or specific Tool.
     */
    public function generateText(
        array $messages,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): TextResult;

    /**
     * Stream text generation.
     *
     * @param Message[] $messages The conversation messages.
     * @param string|null $system Optional system message.
     * @param int|null $maxTokens Maximum tokens to generate.
     * @param float|null $temperature Sampling temperature (0-2).
     * @param float|null $topP Top-p sampling parameter.
     * @param string[]|null $stopSequences Sequences that stop generation.
     * @param Tool[]|null $tools Available tools for the model to use.
     * @param string|Tool|null $toolChoice Control tool usage.
     * @return Generator<TextChunk>
     */
    public function streamText(
        array $messages,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        string|Tool|null $toolChoice = null,
    ): Generator;

    /**
     * Generate a structured object from messages.
     *
     * @param Message[] $messages The conversation messages.
     * @param Schema $schema The schema that defines the expected output structure.
     * @param string|null $system Optional system message.
     * @param int|null $maxTokens Maximum tokens to generate.
     * @param float|null $temperature Sampling temperature (0-2).
     * @param float|null $topP Top-p sampling parameter.
     * @param string[]|null $stopSequences Sequences that stop generation.
     */
    public function generateObject(
        array $messages,
        Schema $schema,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
    ): ObjectResult;

    /**
     * Stream structured object generation.
     *
     * @param Message[] $messages The conversation messages.
     * @param Schema $schema The schema that defines the expected output structure.
     * @param string|null $system Optional system message.
     * @param int|null $maxTokens Maximum tokens to generate.
     * @param float|null $temperature Sampling temperature (0-2).
     * @param float|null $topP Top-p sampling parameter.
     * @param string[]|null $stopSequences Sequences that stop generation.
     * @return Generator<ObjectChunk>
     */
    public function streamObject(
        array $messages,
        Schema $schema,
        ?string $system = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?array $stopSequences = null,
    ): Generator;
}
