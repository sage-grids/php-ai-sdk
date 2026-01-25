<?php

namespace Tests\Unit\Http;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Http\StreamingResponse;

class StreamingResponseTest extends TestCase
{
    public function testParseSimpleEvents(): void
    {
        $content = "data: hello\n\ndata: world\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(2, $events);
        $this->assertEquals('hello', $events[0]->data);
        $this->assertEquals('world', $events[1]->data);
    }

    public function testParseJsonEvents(): void
    {
        $content = "data: {\"foo\": \"bar\"}\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertIsArray($events[0]->data);
        $this->assertEquals('bar', $events[0]->data['foo']);
    }

    public function testParseWithIdAndEvent(): void
    {
        $content = "id: 123\nevent: update\ndata: payload\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals('123', $events[0]->id);
        $this->assertEquals('update', $events[0]->event);
        $this->assertEquals('payload', $events[0]->data);
    }

    public function testHandlesIncompleteChunks(): void
    {
        $content = "data: part1";
        $content2 = "part2\n\n";

        $stream = Utils::streamFor($content . $content2);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());
        $this->assertCount(1, $events);
        $this->assertEquals('part1part2', $events[0]->data);
    }

    public function testCancelStopsProcessing(): void
    {
        $content = "data: first\n\ndata: second\n\ndata: third\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = [];
        foreach ($response->events() as $event) {
            $events[] = $event;
            if (count($events) === 1) {
                $response->cancel();
            }
        }

        $this->assertTrue($response->isCancelled());
        // May get 1 or 2 events depending on timing, but should not get all 3
        $this->assertLessThanOrEqual(2, count($events));
    }

    public function testIsCancelledReturnsFalseInitially(): void
    {
        $stream = Utils::streamFor("data: test\n\n");
        $response = new StreamingResponse($stream);

        $this->assertFalse($response->isCancelled());
    }

    public function testStrictParsingThrowsOnMalformedJson(): void
    {
        $content = "data: {invalid json}\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream, strictParsing: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed SSE: JSON parse error');

        iterator_to_array($response->events());
    }

    public function testStrictParsingThrowsOnMissingTermination(): void
    {
        // Content without proper \n\n termination
        $content = "data: incomplete";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream, strictParsing: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed SSE: stream ended without proper termination');

        iterator_to_array($response->events());
    }

    public function testStrictParsingThrowsOnInvalidRetryField(): void
    {
        $content = "retry: not-a-number\ndata: test\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream, strictParsing: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed SSE: retry field must be an integer');

        iterator_to_array($response->events());
    }

    public function testLenientParsingSkipsMalformedJson(): void
    {
        // Without strict parsing, malformed JSON should be returned as string
        $content = "data: {invalid json}\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream, strictParsing: false);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals('{invalid json}', $events[0]->data);
    }

    public function testParseRetryField(): void
    {
        $content = "retry: 5000\ndata: test\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals(5000, $events[0]->retry);
        $this->assertEquals('test', $events[0]->data);
    }

    public function testCommentsAreIgnored(): void
    {
        $content = ": this is a comment\ndata: actual data\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals('actual data', $events[0]->data);
    }

    public function testMultilineData(): void
    {
        $content = "data: line1\ndata: line2\ndata: line3\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals("line1\nline2\nline3", $events[0]->data);
    }

    public function testEventWithOnlyEventField(): void
    {
        $content = "event: ping\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals('ping', $events[0]->event);
        $this->assertEquals('', $events[0]->data);
    }

    public function testLeadingSpaceAfterColonIsRemoved(): void
    {
        // Per SSE spec, a single space after colon should be removed
        $content = "data: value with leading space\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals('value with leading space', $events[0]->data);
    }

    public function testFieldWithNoValue(): void
    {
        // Field with no colon (valid in SSE spec, value should be empty string)
        $content = "data\n\n";
        $stream = Utils::streamFor($content);
        $response = new StreamingResponse($stream);

        $events = iterator_to_array($response->events());

        $this->assertCount(1, $events);
        $this->assertEquals('', $events[0]->data);
    }
}
