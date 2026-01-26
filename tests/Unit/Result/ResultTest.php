<?php

namespace Tests\Unit\Result;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Result\EmbeddingData;
use SageGrids\PhpAiSdk\Result\EmbeddingResult;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Result\ImageData;
use SageGrids\PhpAiSdk\Result\ImageResult;
use SageGrids\PhpAiSdk\Result\ObjectChunk;
use SageGrids\PhpAiSdk\Result\ObjectResult;
use SageGrids\PhpAiSdk\Result\SpeechResult;
use SageGrids\PhpAiSdk\Result\TextChunk;
use SageGrids\PhpAiSdk\Result\TextResult;
use SageGrids\PhpAiSdk\Result\ToolCall;
use SageGrids\PhpAiSdk\Result\TranscriptionResult;
use SageGrids\PhpAiSdk\Result\TranscriptionSegment;
use SageGrids\PhpAiSdk\Result\Usage;

final class ResultTest extends TestCase
{
    // ========== Usage Tests ==========

    public function testUsageInstantiation(): void
    {
        $usage = new Usage(100, 50, 150);

        $this->assertSame(100, $usage->promptTokens);
        $this->assertSame(50, $usage->completionTokens);
        $this->assertSame(150, $usage->totalTokens);
    }

    public function testUsageFromArray(): void
    {
        // OpenAI format
        $usage = Usage::fromArray([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ]);

        $this->assertSame(100, $usage->promptTokens);
        $this->assertSame(50, $usage->completionTokens);
        $this->assertSame(150, $usage->totalTokens);

        // Anthropic format
        $usage = Usage::fromArray([
            'input_tokens' => 200,
            'output_tokens' => 100,
        ]);

        $this->assertSame(200, $usage->promptTokens);
        $this->assertSame(100, $usage->completionTokens);
        $this->assertSame(300, $usage->totalTokens);
    }

    public function testUsageZero(): void
    {
        $usage = Usage::zero();

        $this->assertSame(0, $usage->promptTokens);
        $this->assertSame(0, $usage->completionTokens);
        $this->assertSame(0, $usage->totalTokens);
    }

    public function testUsageAdd(): void
    {
        $usage1 = new Usage(100, 50, 150);
        $usage2 = new Usage(200, 100, 300);

        $combined = $usage1->add($usage2);

        $this->assertSame(300, $combined->promptTokens);
        $this->assertSame(150, $combined->completionTokens);
        $this->assertSame(450, $combined->totalTokens);
    }

    public function testUsageToArray(): void
    {
        $usage = new Usage(100, 50, 150);
        $array = $usage->toArray();

        $this->assertSame([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ], $array);
    }

    // ========== FinishReason Tests ==========

    public function testFinishReasonValues(): void
    {
        $this->assertSame('stop', FinishReason::Stop->value);
        $this->assertSame('length', FinishReason::Length->value);
        $this->assertSame('tool_calls', FinishReason::ToolCalls->value);
        $this->assertSame('content_filter', FinishReason::ContentFilter->value);
    }

    public function testFinishReasonFromString(): void
    {
        $this->assertSame(FinishReason::Stop, FinishReason::fromString('stop'));
        $this->assertSame(FinishReason::Stop, FinishReason::fromString('end_turn'));
        $this->assertSame(FinishReason::Stop, FinishReason::fromString('complete'));

        $this->assertSame(FinishReason::Length, FinishReason::fromString('length'));
        $this->assertSame(FinishReason::Length, FinishReason::fromString('max_tokens'));

        $this->assertSame(FinishReason::ToolCalls, FinishReason::fromString('tool_calls'));
        $this->assertSame(FinishReason::ToolCalls, FinishReason::fromString('tool_use'));
        $this->assertSame(FinishReason::ToolCalls, FinishReason::fromString('function_call'));

        $this->assertSame(FinishReason::ContentFilter, FinishReason::fromString('content_filter'));
        $this->assertSame(FinishReason::ContentFilter, FinishReason::fromString('safety'));

        $this->assertNull(FinishReason::fromString(null));
        $this->assertNull(FinishReason::fromString('unknown_reason'));
    }

    // ========== ToolCall Tests ==========

    public function testToolCallInstantiation(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['city' => 'London']);

        $this->assertSame('call_123', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['city' => 'London'], $toolCall->arguments);
    }

    public function testToolCallFromArray(): void
    {
        // OpenAI format
        $toolCall = ToolCall::fromArray([
            'id' => 'call_123',
            'function' => ['name' => 'get_weather'],
            'arguments' => '{"city":"London"}',
        ]);

        $this->assertSame('call_123', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['city' => 'London'], $toolCall->arguments);

        // Anthropic format
        $toolCall = ToolCall::fromArray([
            'id' => 'toolu_123',
            'name' => 'get_weather',
            'input' => ['city' => 'Paris'],
        ]);

        $this->assertSame('toolu_123', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['city' => 'Paris'], $toolCall->arguments);
    }

    public function testToolCallToArray(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['city' => 'London']);
        $array = $toolCall->toArray();

        $this->assertSame('call_123', $array['id']);
        $this->assertSame('function', $array['type']);
        $this->assertSame('get_weather', $array['function']['name']);
        $this->assertSame('{"city":"London"}', $array['function']['arguments']);
    }

    // ========== TextResult Tests ==========

    public function testTextResultInstantiation(): void
    {
        $usage = new Usage(100, 50, 150);
        $result = new TextResult(
            text: 'Hello, world!',
            finishReason: FinishReason::Stop,
            usage: $usage,
        );

        $this->assertSame('Hello, world!', $result->text);
        $this->assertSame(FinishReason::Stop, $result->finishReason);
        $this->assertSame($usage, $result->usage);
        $this->assertEmpty($result->toolCalls);
    }

    public function testTextResultWithToolCalls(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['city' => 'London']);
        $result = new TextResult(
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [$toolCall],
        );

        $this->assertTrue($result->hasToolCalls());
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame($toolCall, $result->toolCalls[0]);
    }

    public function testTextResultIsComplete(): void
    {
        $complete = new TextResult('Done', FinishReason::Stop);
        $truncated = new TextResult('Cut off...', FinishReason::Length);

        $this->assertTrue($complete->isComplete());
        $this->assertFalse($complete->isTruncated());

        $this->assertFalse($truncated->isComplete());
        $this->assertTrue($truncated->isTruncated());
    }

    // ========== TextChunk Tests ==========

    public function testTextChunkInstantiation(): void
    {
        $chunk = new TextChunk(
            text: 'Hello',
            delta: 'Hello',
            isComplete: false,
        );

        $this->assertSame('Hello', $chunk->text);
        $this->assertSame('Hello', $chunk->delta);
        $this->assertFalse($chunk->isComplete);
    }

    public function testTextChunkFirst(): void
    {
        $chunk = TextChunk::first('Hello');

        $this->assertSame('Hello', $chunk->text);
        $this->assertSame('Hello', $chunk->delta);
        $this->assertFalse($chunk->isComplete);
    }

    public function testTextChunkContinue(): void
    {
        $chunk = TextChunk::continue('Hello, world', ', world');

        $this->assertSame('Hello, world', $chunk->text);
        $this->assertSame(', world', $chunk->delta);
        $this->assertFalse($chunk->isComplete);
    }

    public function testTextChunkFinal(): void
    {
        $usage = new Usage(100, 50, 150);
        $chunk = TextChunk::final(
            'Hello, world!',
            '!',
            FinishReason::Stop,
            $usage,
        );

        $this->assertSame('Hello, world!', $chunk->text);
        $this->assertSame('!', $chunk->delta);
        $this->assertTrue($chunk->isComplete);
        $this->assertSame(FinishReason::Stop, $chunk->finishReason);
        $this->assertSame($usage, $chunk->usage);
    }

    // ========== ObjectResult Tests ==========

    public function testObjectResultInstantiation(): void
    {
        $object = (object) ['name' => 'John', 'age' => 30];
        $result = new ObjectResult(
            object: $object,
            text: '{"name":"John","age":30}',
            finishReason: FinishReason::Stop,
        );

        $this->assertSame($object, $result->object);
        $this->assertSame('{"name":"John","age":30}', $result->text);
        $this->assertTrue($result->isComplete());
    }

    public function testObjectResultWithArray(): void
    {
        $array = ['items' => [1, 2, 3]];
        $result = new ObjectResult(
            object: $array,
            text: '{"items":[1,2,3]}',
        );

        $this->assertSame($array, $result->object);
    }

    // ========== ObjectChunk Tests ==========

    public function testObjectChunkPartial(): void
    {
        $delta = ['name' => 'Jo'];
        $chunk = ObjectChunk::partial($delta, '{"name":"Jo');

        $this->assertSame($delta, $chunk->delta);
        $this->assertSame('{"name":"Jo', $chunk->text);
        $this->assertFalse($chunk->isComplete);
    }

    public function testObjectChunkFinal(): void
    {
        $delta = ['name' => 'John', 'age' => 30];
        $usage = new Usage(100, 50, 150);
        $chunk = ObjectChunk::final(
            $delta,
            '{"name":"John","age":30}',
            FinishReason::Stop,
            $usage,
        );

        $this->assertSame($delta, $chunk->delta);
        $this->assertTrue($chunk->isComplete);
        $this->assertSame($usage, $chunk->usage);
    }

    // ========== ImageData Tests ==========

    public function testImageDataInstantiation(): void
    {
        $data = new ImageData(
            url: 'https://example.com/image.png',
            revisedPrompt: 'A revised prompt',
        );

        $this->assertSame('https://example.com/image.png', $data->url);
        $this->assertNull($data->base64);
        $this->assertSame('A revised prompt', $data->revisedPrompt);
        $this->assertTrue($data->hasUrl());
        $this->assertFalse($data->hasBase64());
    }

    public function testImageDataFromArray(): void
    {
        $data = ImageData::fromArray([
            'url' => 'https://example.com/image.png',
            'revised_prompt' => 'Revised',
        ]);

        $this->assertSame('https://example.com/image.png', $data->url);
        $this->assertSame('Revised', $data->revisedPrompt);

        $data = ImageData::fromArray([
            'b64_json' => 'base64encodeddata',
        ]);

        $this->assertNull($data->url);
        $this->assertSame('base64encodeddata', $data->base64);
    }

    // ========== ImageResult Tests ==========

    public function testImageResultInstantiation(): void
    {
        $images = [
            new ImageData(url: 'https://example.com/1.png'),
            new ImageData(url: 'https://example.com/2.png'),
        ];
        $result = new ImageResult($images);

        $this->assertCount(2, $result->images);
        $this->assertSame(2, $result->count());
        $this->assertSame($images[0], $result->first());
    }

    // ========== SpeechResult Tests ==========

    public function testSpeechResultInstantiation(): void
    {
        $result = new SpeechResult(
            audio: 'binary audio data',
            format: 'mp3',
            duration: 5.5,
        );

        $this->assertSame('binary audio data', $result->audio);
        $this->assertSame('mp3', $result->format);
        $this->assertSame(5.5, $result->duration);
        $this->assertSame(17, $result->getContentLength());
    }

    // ========== TranscriptionSegment Tests ==========

    public function testTranscriptionSegmentInstantiation(): void
    {
        $segment = new TranscriptionSegment(
            id: 0,
            start: 0.0,
            end: 2.5,
            text: 'Hello world',
        );

        $this->assertSame(0, $segment->id);
        $this->assertSame(0.0, $segment->start);
        $this->assertSame(2.5, $segment->end);
        $this->assertSame('Hello world', $segment->text);
        $this->assertSame(2.5, $segment->getDuration());
    }

    public function testTranscriptionSegmentFromArray(): void
    {
        $segment = TranscriptionSegment::fromArray([
            'id' => 1,
            'start' => 1.0,
            'end' => 3.5,
            'text' => 'Test segment',
        ]);

        $this->assertSame(1, $segment->id);
        $this->assertSame(1.0, $segment->start);
        $this->assertSame(3.5, $segment->end);
        $this->assertSame('Test segment', $segment->text);
    }

    // ========== TranscriptionResult Tests ==========

    public function testTranscriptionResultInstantiation(): void
    {
        $segments = [
            new TranscriptionSegment(0, 0.0, 2.0, 'Hello'),
            new TranscriptionSegment(1, 2.0, 4.0, 'world'),
        ];
        $result = new TranscriptionResult(
            text: 'Hello world',
            language: 'en',
            duration: 4.0,
            segments: $segments,
        );

        $this->assertSame('Hello world', $result->text);
        $this->assertSame('en', $result->language);
        $this->assertSame(4.0, $result->duration);
        $this->assertTrue($result->hasSegments());
        $this->assertSame(2, $result->getSegmentCount());
    }

    // ========== EmbeddingData Tests ==========

    public function testEmbeddingDataInstantiation(): void
    {
        $embedding = [0.1, 0.2, 0.3, 0.4];
        $data = new EmbeddingData(index: 0, embedding: $embedding);

        $this->assertSame(0, $data->index);
        $this->assertSame($embedding, $data->embedding);
        $this->assertSame(4, $data->getDimension());
    }

    public function testEmbeddingDataFromArray(): void
    {
        $data = EmbeddingData::fromArray([
            'index' => 1,
            'embedding' => [0.5, 0.6, 0.7],
        ]);

        $this->assertSame(1, $data->index);
        $this->assertSame([0.5, 0.6, 0.7], $data->embedding);
    }

    public function testEmbeddingDataCosineSimilarity(): void
    {
        $data1 = new EmbeddingData(0, [1.0, 0.0, 0.0]);
        $data2 = new EmbeddingData(1, [1.0, 0.0, 0.0]);
        $data3 = new EmbeddingData(2, [0.0, 1.0, 0.0]);

        // Same vectors should have similarity of 1.0
        $this->assertEqualsWithDelta(1.0, $data1->cosineSimilarity($data2), 0.0001);

        // Orthogonal vectors should have similarity of 0.0
        $this->assertEqualsWithDelta(0.0, $data1->cosineSimilarity($data3), 0.0001);
    }

    // ========== EmbeddingResult Tests ==========

    public function testEmbeddingResultInstantiation(): void
    {
        $embeddings = [
            new EmbeddingData(0, [0.1, 0.2]),
            new EmbeddingData(1, [0.3, 0.4]),
        ];
        $result = new EmbeddingResult(
            embeddings: $embeddings,
            model: 'text-embedding-ada-002',
        );

        $this->assertSame($embeddings, $result->embeddings);
        $this->assertSame('text-embedding-ada-002', $result->model);
        $this->assertSame(2, $result->count());
        $this->assertSame($embeddings[0], $result->first());
        $this->assertSame($embeddings[1], $result->get(1));
    }

    public function testEmbeddingResultToVectors(): void
    {
        $embeddings = [
            new EmbeddingData(0, [0.1, 0.2]),
            new EmbeddingData(1, [0.3, 0.4]),
        ];
        $result = new EmbeddingResult($embeddings, 'model');

        $vectors = $result->toVectors();

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $vectors);
    }

    // ========== Immutability Tests ==========

    public function testUsageIsImmutable(): void
    {
        $usage = new Usage(100, 50, 150);

        $this->expectException(\Error::class);
        $usage->promptTokens = 200; // @phpstan-ignore-line
    }

    public function testTextResultIsImmutable(): void
    {
        $result = new TextResult('Hello');

        $this->expectException(\Error::class);
        $result->text = 'Modified'; // @phpstan-ignore-line
    }

    public function testToolCallIsImmutable(): void
    {
        $toolCall = new ToolCall('id', 'name', []);

        $this->expectException(\Error::class);
        $toolCall->name = 'modified'; // @phpstan-ignore-line
    }
}
