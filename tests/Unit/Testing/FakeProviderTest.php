<?php

namespace Tests\Unit\Testing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Result\FinishReason;
use SageGrids\PhpAiSdk\Testing\FakeProvider;
use SageGrids\PhpAiSdk\Testing\FakeResponse;

final class FakeProviderTest extends TestCase
{
    private FakeProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new FakeProvider();
    }

    public function testGetNameReturnsConfiguredName(): void
    {
        $provider = new FakeProvider('custom-name');
        $this->assertEquals('custom-name', $provider->getName());
    }

    public function testGetCapabilitiesReturnsAllEnabled(): void
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTextGeneration);
        $this->assertTrue($capabilities->supportsStreaming);
        $this->assertTrue($capabilities->supportsStructuredOutput);
        $this->assertTrue($capabilities->supportsToolCalling);
        $this->assertTrue($capabilities->supportsImageGeneration);
        $this->assertTrue($capabilities->supportsSpeechGeneration);
        $this->assertTrue($capabilities->supportsTranscription);
        $this->assertTrue($capabilities->supportsEmbeddings);
        $this->assertTrue($capabilities->supportsVision);
    }

    public function testGetAvailableModels(): void
    {
        $models = $this->provider->getAvailableModels();
        $this->assertContains('fake-model', $models);
    }

    public function testAddTextResponseQueuesResponse(): void
    {
        $this->provider->addTextResponse('Hello, world!');

        $result = $this->provider->generateText([new UserMessage('Hi')]);

        $this->assertEquals('Hello, world!', $result->text);
        $this->assertEquals(FinishReason::Stop, $result->finishReason);
    }

    public function testAddTextResponseWithTextResult(): void
    {
        $customResult = FakeResponse::text('Custom response', FakeResponse::usage(20, 10));

        $this->provider->addTextResponse($customResult);

        $result = $this->provider->generateText([new UserMessage('Hi')]);

        $this->assertEquals('Custom response', $result->text);
        $this->assertEquals(20, $result->usage->promptTokens);
        $this->assertEquals(10, $result->usage->completionTokens);
    }

    public function testMultipleResponsesAreReturnedInOrder(): void
    {
        $this->provider->addTextResponse('First');
        $this->provider->addTextResponse('Second');
        $this->provider->addTextResponse('Third');

        $this->assertEquals('First', $this->provider->generateText([new UserMessage('1')])->text);
        $this->assertEquals('Second', $this->provider->generateText([new UserMessage('2')])->text);
        $this->assertEquals('Third', $this->provider->generateText([new UserMessage('3')])->text);
    }

    public function testDefaultResponseWhenQueueEmpty(): void
    {
        $result = $this->provider->generateText([new UserMessage('Hi')]);

        $this->assertEquals('', $result->text);
    }

    public function testAddObjectResponse(): void
    {
        $this->provider->addObjectResponse(['name' => 'John', 'age' => 30]);

        $result = $this->provider->generateObject(
            [new UserMessage('Generate')],
            Schema::object(['name' => Schema::string(), 'age' => Schema::integer()]),
        );

        $this->assertEquals(['name' => 'John', 'age' => 30], $result->object);
    }

    public function testAddEmbeddingResponse(): void
    {
        $this->provider->addEmbeddingResponse([0.1, 0.2, 0.3]);

        $result = $this->provider->embed('Hello');

        $this->assertCount(1, $result->embeddings);
        $this->assertEquals([0.1, 0.2, 0.3], $result->first()->embedding);
    }

    public function testAddImageResponse(): void
    {
        $this->provider->addImageResponse('https://example.com/image.png');

        $result = $this->provider->generateImage('A cat');

        $this->assertEquals('https://example.com/image.png', $result->first()->url);
    }

    public function testAddSpeechResponse(): void
    {
        $this->provider->addSpeechResponse('audio-data');

        $result = $this->provider->generateSpeech('Hello', 'alloy');

        $this->assertEquals('audio-data', $result->audio);
    }

    public function testAddTranscriptionResponse(): void
    {
        $this->provider->addTranscriptionResponse('Hello, world!');

        $result = $this->provider->transcribe('/path/to/audio.mp3');

        $this->assertEquals('Hello, world!', $result->text);
    }

    public function testStreamTextReturnsChunks(): void
    {
        $this->provider->addTextStreamResponse(['Hello', ', ', 'world', '!']);

        $chunks = [];
        foreach ($this->provider->streamText([new UserMessage('Hi')]) as $chunk) {
            $chunks[] = $chunk->delta;
        }

        $this->assertEquals(['Hello', ', ', 'world', '!'], $chunks);
    }

    public function testStreamObjectReturnsChunks(): void
    {
        $this->provider->addObjectStreamResponse(['name' => 'John']);

        $lastChunk = null;
        foreach ($this->provider->streamObject(
            [new UserMessage('Generate')],
            Schema::object(['name' => Schema::string()]),
        ) as $chunk) {
            $lastChunk = $chunk;
        }

        $this->assertTrue($lastChunk->isComplete);
        $this->assertEquals(['name' => 'John'], $lastChunk->delta);
    }

    public function testRequestsAreRecorded(): void
    {
        $this->provider->addTextResponse('Response');

        $this->provider->generateText(
            [new UserMessage('Test message')],
            'System prompt',
            100,
            0.7,
        );

        $requests = $this->provider->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('generateText', $request->operation);
        $this->assertCount(1, $request->messages);
        $this->assertEquals('System prompt', $request->system);
        $this->assertEquals(100, $request->maxTokens);
        $this->assertEquals(0.7, $request->temperature);
    }

    public function testGetLastRequest(): void
    {
        $this->provider->addTextResponse('1');
        $this->provider->addTextResponse('2');

        $this->provider->generateText([new UserMessage('First')]);
        $this->provider->generateText([new UserMessage('Second')]);

        $lastRequest = $this->provider->getLastRequest();
        $this->assertEquals('Second', $lastRequest->getFirstMessageContent());
    }

    public function testGetRequestsFor(): void
    {
        $this->provider->addTextResponse('Text');
        $this->provider->addEmbeddingResponse([0.1]);

        $this->provider->generateText([new UserMessage('Text')]);
        $this->provider->embed('Embed');

        $textRequests = $this->provider->getRequestsFor('generateText');
        $embedRequests = $this->provider->getRequestsFor('embed');

        $this->assertCount(1, $textRequests);
        $this->assertCount(1, $embedRequests);
        $this->assertEquals('generateText', $textRequests[0]->operation);
        $this->assertEquals('embed', $embedRequests[0]->operation);
    }

    public function testGetRequestCount(): void
    {
        $this->provider->addTextResponse('1');
        $this->provider->addTextResponse('2');
        $this->provider->addEmbeddingResponse([0.1]);

        $this->provider->generateText([new UserMessage('1')]);
        $this->provider->generateText([new UserMessage('2')]);
        $this->provider->embed('3');

        $this->assertEquals(3, $this->provider->getRequestCount());
        $this->assertEquals(2, $this->provider->getRequestCount('generateText'));
        $this->assertEquals(1, $this->provider->getRequestCount('embed'));
    }

    public function testAssertRequestMadeSucceeds(): void
    {
        $this->provider->addTextResponse('Response');
        $this->provider->generateText([new UserMessage('Test')]);

        $result = $this->provider->assertRequestMade('generateText');
        $this->assertTrue($result);
    }

    public function testAssertRequestMadeFailsWhenNoRequests(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No requests found for operation');

        $this->provider->assertRequestMade('generateText');
    }

    public function testAssertRequestMadeWithCallback(): void
    {
        $this->provider->addTextResponse('Response');
        $this->provider->generateText([new UserMessage('Hello world')]);

        $result = $this->provider->assertRequestMade('generateText', function ($request) {
            return $request->hasMessageContent('Hello');
        });

        $this->assertTrue($result);
    }

    public function testAssertRequestMadeFailsWhenCallbackFails(): void
    {
        $this->provider->addTextResponse('Response');
        $this->provider->generateText([new UserMessage('Hello')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No request for');

        $this->provider->assertRequestMade('generateText', function ($request) {
            return $request->hasMessageContent('Goodbye');
        });
    }

    public function testAssertRequestCountSucceeds(): void
    {
        $this->provider->addTextResponse('1');
        $this->provider->addTextResponse('2');

        $this->provider->generateText([new UserMessage('1')]);
        $this->provider->generateText([new UserMessage('2')]);

        $result = $this->provider->assertRequestCount(2);
        $this->assertTrue($result);
    }

    public function testAssertRequestCountFails(): void
    {
        $this->provider->addTextResponse('1');
        $this->provider->generateText([new UserMessage('1')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 2 requests');

        $this->provider->assertRequestCount(2);
    }

    public function testClearRequests(): void
    {
        $this->provider->addTextResponse('Response');
        $this->provider->generateText([new UserMessage('Test')]);

        $this->assertCount(1, $this->provider->getRequests());

        $this->provider->clearRequests();

        $this->assertCount(0, $this->provider->getRequests());
    }

    public function testClearResponses(): void
    {
        $this->provider->addTextResponse('Response');
        $this->provider->clearResponses();

        // Should return default empty response
        $result = $this->provider->generateText([new UserMessage('Test')]);
        $this->assertEquals('', $result->text);
    }

    public function testReset(): void
    {
        $this->provider->addTextResponse('Response');
        $this->provider->generateText([new UserMessage('Test')]);

        $this->provider->reset();

        $this->assertCount(0, $this->provider->getRequests());
        // Should return default response
        $result = $this->provider->generateText([new UserMessage('Test')]);
        $this->assertEquals('', $result->text);
    }

    public function testFluentInterface(): void
    {
        $result = $this->provider
            ->addTextResponse('1')
            ->addTextResponse('2')
            ->addObjectResponse(['key' => 'value'])
            ->clearRequests()
            ->clearResponses()
            ->reset();

        $this->assertSame($this->provider, $result);
    }
}
