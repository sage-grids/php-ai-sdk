<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk;

use Generator;
use SageGrids\PhpAiSdk\Core\Functions\GenerateObject;
use SageGrids\PhpAiSdk\Core\Functions\GenerateText;
use SageGrids\PhpAiSdk\Core\Functions\StreamObject;
use SageGrids\PhpAiSdk\Core\Functions\StreamText;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;

/**
 * Main AI static facade class for text and object generation.
 *
 * This class provides a simple, static API for AI operations similar to the
 * Vercel AI SDK. It supports text generation, streaming, structured output,
 * and tool calling.
 *
 * Basic usage:
 * ```php
 * // Simple text generation
 * $result = AI::generateText([
 *     'model' => 'openai/gpt-4o',
 *     'prompt' => 'What is the meaning of life?',
 * ]);
 * echo $result->text;
 *
 * // Streaming text
 * foreach (AI::streamText(['model' => 'openai/gpt-4o', 'prompt' => 'Tell me a story']) as $chunk) {
 *     echo $chunk->delta;
 * }
 *
 * // Structured output
 * $result = AI::generateObject([
 *     'model' => 'openai/gpt-4o',
 *     'prompt' => 'Generate a user profile',
 *     'schema' => Schema::object([
 *         'name' => Schema::string(),
 *         'age' => Schema::integer(),
 *     ]),
 * ]);
 * echo $result->object['name'];
 * ```
 *
 * @see https://ai-sdk.dev for API documentation
 */
final class AI
{
    private function __construct()
    {
        // Prevent instantiation
    }

    /**
     * Generate text from a prompt or messages.
     *
     * This method handles synchronous text generation, including automatic
     * tool execution if tools are provided and called by the model.
     *
     * Options:
     * - model: string|ProviderInterface - The model to use (e.g., 'openai/gpt-4o')
     * - prompt: ?string - A simple text prompt
     * - messages: ?array<Message> - Conversation messages
     * - system: ?string - System message
     * - maxTokens: ?int - Maximum tokens to generate
     * - temperature: ?float - Sampling temperature (0-2)
     * - topP: ?float - Top-p sampling parameter
     * - stopSequences: ?array<string> - Sequences that stop generation
     * - tools: ?array<Tool> - Available tools for the model
     * - toolChoice: ?string|Tool - Control tool usage: 'auto', 'none', 'required', or specific Tool
     * - maxToolRoundtrips: ?int - Maximum tool execution rounds (default: 5)
     * - onFinish: ?callable - Callback when generation completes
     *
     * @param array<string, mixed> $options Generation options.
     * @return TextResult The generation result containing text, usage, and tool calls.
     *
     * @throws \SageGrids\PhpAiSdk\Exception\InputValidationException If required parameters are missing.
     * @throws \SageGrids\PhpAiSdk\Exception\ProviderException If the provider returns an error.
     */
    public static function generateText(array $options): TextResult
    {
        return GenerateText::create($options)->execute();
    }

    /**
     * Stream text generation.
     *
     * This method returns a generator that yields text chunks as they are
     * generated, allowing for real-time streaming of the response.
     *
     * Options:
     * - model: string|ProviderInterface - The model to use (e.g., 'openai/gpt-4o')
     * - prompt: ?string - A simple text prompt
     * - messages: ?array<Message> - Conversation messages
     * - system: ?string - System message
     * - maxTokens: ?int - Maximum tokens to generate
     * - temperature: ?float - Sampling temperature (0-2)
     * - topP: ?float - Top-p sampling parameter
     * - stopSequences: ?array<string> - Sequences that stop generation
     * - tools: ?array<Tool> - Available tools for the model
     * - toolChoice: ?string|Tool - Control tool usage
     * - onChunk: ?callable - Callback for each chunk
     * - onFinish: ?callable - Callback when generation completes
     *
     * @param array<string, mixed> $options Generation options.
     * @return Generator<TextChunk> Generator yielding text chunks.
     *
     * @throws \SageGrids\PhpAiSdk\Exception\InputValidationException If required parameters are missing.
     * @throws \SageGrids\PhpAiSdk\Exception\ProviderException If the provider returns an error.
     */
    public static function streamText(array $options): Generator
    {
        return StreamText::create($options)->execute();
    }

    /**
     * Generate a structured object from a prompt.
     *
     * This method uses a schema to ensure the model returns structured data
     * that matches the expected format.
     *
     * Options:
     * - model: string|ProviderInterface - The model to use (e.g., 'openai/gpt-4o')
     * - prompt: ?string - A simple text prompt
     * - messages: ?array<Message> - Conversation messages
     * - system: ?string - System message
     * - schema: Schema|class-string - The expected output schema
     * - schemaName: ?string - Name for the schema (for context)
     * - schemaDescription: ?string - Description of the schema (for context)
     * - maxTokens: ?int - Maximum tokens to generate
     * - temperature: ?float - Sampling temperature (0-2)
     * - topP: ?float - Top-p sampling parameter
     * - stopSequences: ?array<string> - Sequences that stop generation
     * - onFinish: ?callable - Callback when generation completes
     *
     * @param array<string, mixed> $options Generation options.
     * @return ObjectResult<mixed> The generation result containing the parsed object.
     *
     * @throws \SageGrids\PhpAiSdk\Exception\InputValidationException If required parameters are missing.
     * @throws \SageGrids\PhpAiSdk\Exception\SchemaValidationException If the response doesn't match the schema.
     * @throws \SageGrids\PhpAiSdk\Exception\ProviderException If the provider returns an error.
     */
    public static function generateObject(array $options): ObjectResult
    {
        return GenerateObject::create($options)->execute();
    }

    /**
     * Stream structured object generation.
     *
     * This method returns a generator that yields partial objects as they
     * are being generated. Note that partial objects may not be valid until
     * the final chunk.
     *
     * Options:
     * - model: string|ProviderInterface - The model to use (e.g., 'openai/gpt-4o')
     * - prompt: ?string - A simple text prompt
     * - messages: ?array<Message> - Conversation messages
     * - system: ?string - System message
     * - schema: Schema|class-string - The expected output schema
     * - schemaName: ?string - Name for the schema (for context)
     * - schemaDescription: ?string - Description of the schema (for context)
     * - maxTokens: ?int - Maximum tokens to generate
     * - temperature: ?float - Sampling temperature (0-2)
     * - topP: ?float - Top-p sampling parameter
     * - stopSequences: ?array<string> - Sequences that stop generation
     * - onChunk: ?callable - Callback for each chunk
     * - onFinish: ?callable - Callback when generation completes
     *
     * @param array<string, mixed> $options Generation options.
     * @return Generator<ObjectChunk<mixed>> Generator yielding object chunks.
     *
     * @throws \SageGrids\PhpAiSdk\Exception\InputValidationException If required parameters are missing.
     * @throws \SageGrids\PhpAiSdk\Exception\SchemaValidationException If the final response doesn't match the schema.
     * @throws \SageGrids\PhpAiSdk\Exception\ProviderException If the provider returns an error.
     */
    public static function streamObject(array $options): Generator
    {
        return StreamObject::create($options)->execute();
    }
}
