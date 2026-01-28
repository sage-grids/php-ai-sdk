<?php

namespace Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Result\EmbeddingResult;
use SageGrids\PhpAiSdk\Result\FinishReason;
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
use SageGrids\PhpAiSdk\Testing\FakeResponse;

final class FakeResponseTest extends TestCase
{
    public function testTextCreatesTextResult(): void
    {
        $result = FakeResponse::text('Hello, world!');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Hello, world!', $result->text);
        $this->assertEquals(FinishReason::Stop, $result->finishReason);
        $this->assertNotNull($result->usage);
    }

    public function testTextWithCustomUsage(): void
    {
        $usage = new Usage(20, 10, 30);
        $result = FakeResponse::text('Hello', $usage);

        $this->assertEquals(20, $result->usage->promptTokens);
        $this->assertEquals(10, $result->usage->completionTokens);
        $this->assertEquals(30, $result->usage->totalTokens);
    }

    public function testTextWithFinishReason(): void
    {
        $result = FakeResponse::text('Hello', null, FinishReason::Length);

        $this->assertEquals(FinishReason::Length, $result->finishReason);
    }

    public function testTextWithToolCalls(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'get_weather', ['city' => 'Paris']),
        ];

        $result = FakeResponse::text('', null, null, $toolCalls);

        $this->assertCount(1, $result->toolCalls);
        $this->assertEquals('get_weather', $result->toolCalls[0]->name);
    }

    public function testToolCallsCreatesResultWithToolCalls(): void
    {
        $result = FakeResponse::toolCalls([
            FakeResponse::toolCall('get_weather', ['city' => 'Paris']),
        ]);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals(FinishReason::ToolCalls, $result->finishReason);
        $this->assertTrue($result->hasToolCalls());
        $this->assertEquals('get_weather', $result->toolCalls[0]->name);
    }

    public function testToolCallCreatesToolCall(): void
    {
        $toolCall = FakeResponse::toolCall('my_function', ['arg1' => 'value1']);

        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertEquals('my_function', $toolCall->name);
        $this->assertEquals(['arg1' => 'value1'], $toolCall->arguments);
        $this->assertNotEmpty($toolCall->id);
    }

    public function testToolCallWithCustomId(): void
    {
        $toolCall = FakeResponse::toolCall('func', [], 'custom_id_123');

        $this->assertEquals('custom_id_123', $toolCall->id);
    }

    public function testStreamedTextCreatesTextChunks(): void
    {
        $chunks = FakeResponse::streamedText(['Hello', ', ', 'world', '!']);

        $this->assertCount(4, $chunks);
        $this->assertContainsOnlyInstancesOf(TextChunk::class, $chunks);

        // First chunk
        $this->assertEquals('Hello', $chunks[0]->delta);
        $this->assertEquals('Hello', $chunks[0]->text);
        $this->assertFalse($chunks[0]->isComplete);

        // Middle chunks
        $this->assertEquals(', ', $chunks[1]->delta);
        $this->assertEquals('Hello, ', $chunks[1]->text);

        // Last chunk
        $this->assertEquals('!', $chunks[3]->delta);
        $this->assertEquals('Hello, world!', $chunks[3]->text);
        $this->assertTrue($chunks[3]->isComplete);
        $this->assertEquals(FinishReason::Stop, $chunks[3]->finishReason);
    }

    public function testObjectCreatesObjectResult(): void
    {
        $result = FakeResponse::object(['name' => 'John', 'age' => 30]);

        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertEquals(['name' => 'John', 'age' => 30], $result->object);
        $this->assertEquals('{"name":"John","age":30}', $result->text);
        $this->assertEquals(FinishReason::Stop, $result->finishReason);
    }

    public function testObjectWithCustomText(): void
    {
        $result = FakeResponse::object(['key' => 'value'], '{"key": "value"}');

        $this->assertEquals('{"key": "value"}', $result->text);
    }

    public function testStreamedObjectCreatesObjectChunks(): void
    {
        $chunks = FakeResponse::streamedObject(
            finalObject: ['name' => 'John'],
            partialObjects: [['name' => 'Jo']],
        );

        $this->assertCount(2, $chunks);
        $this->assertContainsOnlyInstancesOf(ObjectChunk::class, $chunks);

        // Partial chunk
        $this->assertEquals(['name' => 'Jo'], $chunks[0]->delta);
        $this->assertFalse($chunks[0]->isComplete);

        // Final chunk
        $this->assertEquals(['name' => 'John'], $chunks[1]->delta);
        $this->assertTrue($chunks[1]->isComplete);
    }

    public function testEmbeddingCreatesSingleEmbedding(): void
    {
        $result = FakeResponse::embedding([0.1, 0.2, 0.3]);

        $this->assertInstanceOf(EmbeddingResult::class, $result);
        $this->assertCount(1, $result->embeddings);
        $this->assertEquals([0.1, 0.2, 0.3], $result->first()->embedding);
    }

    public function testEmbeddingCreatesMultipleEmbeddings(): void
    {
        $result = FakeResponse::embedding([
            [0.1, 0.2],
            [0.3, 0.4],
        ]);

        $this->assertCount(2, $result->embeddings);
        $this->assertEquals([0.1, 0.2], $result->get(0)->embedding);
        $this->assertEquals([0.3, 0.4], $result->get(1)->embedding);
    }

    public function testRandomEmbedding(): void
    {
        $embedding = FakeResponse::randomEmbedding(100);

        $this->assertCount(100, $embedding);

        // Check it's normalized (magnitude should be ~1)
        $magnitude = sqrt(array_sum(array_map(fn ($v) => $v * $v, $embedding)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testRandomEmbeddingWithSeed(): void
    {
        $embedding1 = FakeResponse::randomEmbedding(10, 42);
        $embedding2 = FakeResponse::randomEmbedding(10, 42);

        // Same seed should produce same results
        $this->assertEquals($embedding1, $embedding2);
    }

    public function testImageCreatesImageResult(): void
    {
        $result = FakeResponse::image('https://example.com/image.png');

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertEquals('https://example.com/image.png', $result->first()->url);
    }

    public function testImageWithBase64(): void
    {
        $result = FakeResponse::image(null, 'base64-data');

        $this->assertEquals('base64-data', $result->first()->base64);
    }

    public function testImageWithMultiple(): void
    {
        $result = FakeResponse::image(count: 3);

        $this->assertEquals(3, $result->count());
    }

    public function testSpeechCreatesSpeechResult(): void
    {
        $result = FakeResponse::speech('audio-content', 'mp3', 2.5);

        $this->assertInstanceOf(SpeechResult::class, $result);
        $this->assertEquals('audio-content', $result->audio);
        $this->assertEquals('mp3', $result->format);
        $this->assertEquals(2.5, $result->duration);
    }

    public function testTranscriptionCreatesTranscriptionResult(): void
    {
        $result = FakeResponse::transcription('Hello, world!', 'en', 5.0);

        $this->assertInstanceOf(TranscriptionResult::class, $result);
        $this->assertEquals('Hello, world!', $result->text);
        $this->assertEquals('en', $result->language);
        $this->assertEquals(5.0, $result->duration);
    }

    public function testTranscriptionSegmentCreatesSegment(): void
    {
        $segment = FakeResponse::transcriptionSegment('Hello', 0.0, 1.5, 1);

        $this->assertInstanceOf(TranscriptionSegment::class, $segment);
        $this->assertEquals('Hello', $segment->text);
        $this->assertEquals(0.0, $segment->start);
        $this->assertEquals(1.5, $segment->end);
        $this->assertEquals(1, $segment->id);
    }

    public function testUsageCreatesUsage(): void
    {
        $usage = FakeResponse::usage(100, 50);

        $this->assertInstanceOf(Usage::class, $usage);
        $this->assertEquals(100, $usage->promptTokens);
        $this->assertEquals(50, $usage->completionTokens);
        $this->assertEquals(150, $usage->totalTokens);
    }

    public function testUsageWithCustomTotal(): void
    {
        $usage = FakeResponse::usage(100, 50, 200);

        $this->assertEquals(200, $usage->totalTokens);
    }
}
