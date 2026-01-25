<?php

namespace Tests\Unit\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Http\GuzzleHttpClient;
use SageGrids\PhpAiSdk\Http\MultipartBody;
use SageGrids\PhpAiSdk\Http\Request;

class GuzzleHttpClientTest extends TestCase
{
    public function testRequestReturnsResponse(): void
    {
        $mock = new MockHandler([
            new GuzzleResponse(200, ['X-Foo' => 'Bar'], 'Hello, World'),
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $response = $client->request($request);

        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals('Hello, World', $response->body);
        $this->assertEquals('Bar', $response->headers['X-Foo'][0]);
    }

    public function testMultipartRequest(): void
    {
        $capturedRequest = null;
        $mock = new MockHandler([
            function (PsrRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return new GuzzleResponse(200);
            },
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $multipart = new MultipartBody([
            ['name' => 'file', 'contents' => 'abc']
        ]);
        $request = new Request('POST', 'https://example.com', [], $multipart);

        $client->request($request);

        // Guzzle sets multipart/form-data content-type when processing multipart body
        $this->assertNotNull($capturedRequest);
        $contentType = $capturedRequest->getHeaderLine('Content-Type');
        $this->assertStringContainsString('multipart/form-data', $contentType);
    }

    public function testRetryOnSpecificServerErrors(): void
    {
        // Test that only specific status codes trigger retry (502, 503, 504)
        $mock = new MockHandler([
            new GuzzleResponse(503), // Should retry
            new GuzzleResponse(502), // Should retry
            new GuzzleResponse(200, [], 'Success'),
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $response = $client->request($request);

        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals('Success', $response->body);
    }

    public function testNoRetryOn500InternalServerError(): void
    {
        // 500 Internal Server Error should NOT be retried (not in the specific list)
        $mock = new MockHandler([
            new GuzzleResponse(500, [], 'Internal Server Error'),
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $response = $client->request($request);

        // Should return immediately without retry
        $this->assertEquals(500, $response->statusCode);
    }

    public function testRetryOn429RateLimit(): void
    {
        $mock = new MockHandler([
            new GuzzleResponse(429, [], 'Rate limited'),
            new GuzzleResponse(200, [], 'Success'),
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $response = $client->request($request);

        $this->assertEquals(200, $response->statusCode);
    }

    public function testRetryOnConnectException(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection error', new PsrRequest('GET', 'test')),
            new GuzzleResponse(200, [], 'Success'),
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $response = $client->request($request);

        $this->assertEquals(200, $response->statusCode);
    }

    public function testStreamDoesNotRetry(): void
    {
        // Streaming requests should NOT retry to prevent data corruption
        $requestCount = 0;
        $mock = new MockHandler([
            function () use (&$requestCount) {
                $requestCount++;
                // Return a 503 which would normally trigger retry
                return new GuzzleResponse(503, [], 'Service Unavailable');
            },
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $streamResponse = $client->stream($request);

        // Consume the stream to complete the request
        iterator_to_array($streamResponse->events());

        // Should have only made ONE request (no retries for streaming)
        $this->assertEquals(1, $requestCount);
    }

    public function testStreamSuccessfulRequest(): void
    {
        $sseContent = "data: hello\n\ndata: world\n\n";
        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'text/event-stream'], $sseContent),
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com/stream');

        $streamResponse = $client->stream($request);
        $events = iterator_to_array($streamResponse->events());

        $this->assertCount(2, $events);
        $this->assertEquals('hello', $events[0]->data);
        $this->assertEquals('world', $events[1]->data);
    }

    public function testMaxRetriesExceeded(): void
    {
        $mock = new MockHandler([
            new GuzzleResponse(503),
            new GuzzleResponse(503),
            new GuzzleResponse(503),
            new GuzzleResponse(503), // This is the 4th, exceeds maxRetries=3
        ]);

        $client = new GuzzleHttpClient(30, 3, $mock);
        $request = new Request('GET', 'https://example.com');

        $response = $client->request($request);

        // After 3 retries (4 total attempts), should return the error response
        $this->assertEquals(503, $response->statusCode);
    }
}
