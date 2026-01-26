<?php

namespace SageGrids\PhpAiSdk\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Throwable;

class GuzzleHttpClient implements HttpClientInterface
{
    private Client $client;
    private Client $streamClient;

    /**
     * Retryable HTTP status codes for transient errors.
     */
    private const RETRYABLE_STATUS_CODES = [429, 502, 503, 504];

    /**
     * Stores the last response for extracting Retry-After header.
     */
    private ?PsrResponseInterface $lastResponse = null;

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $maxRetries = 3,
        ?callable $handler = null
    ) {
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        $this->client = new Client([
            'handler' => $stack,
            'timeout' => $this->timeout,
        ]);

        // Separate client for streaming WITHOUT retry middleware
        // Retrying a stream after receiving data would cause corruption/duplication
        $streamStack = HandlerStack::create($handler);
        $this->streamClient = new Client([
            'handler' => $streamStack,
            'timeout' => $this->timeout,
        ]);
    }

    public function request(Request $request): Response
    {
        $options = $this->buildOptions($request);
        $options[RequestOptions::STREAM] = false;

        try {
            $guzzleResponse = $this->client->request(
                $request->method,
                $request->uri,
                $options
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $guzzleResponse = $e->getResponse();
            } else {
                throw $e;
            }
        }

        return new Response(
            $guzzleResponse->getStatusCode(),
            $guzzleResponse->getHeaders(),
            (string) $guzzleResponse->getBody()
        );
    }

    public function stream(Request $request): StreamingResponse
    {
        $options = $this->buildOptions($request);
        $options[RequestOptions::STREAM] = true;

        // Use streamClient WITHOUT retry middleware to prevent data corruption
        // Once streaming starts, retrying would duplicate or corrupt data
        try {
            $guzzleResponse = $this->streamClient->request(
                $request->method,
                $request->uri,
                $options
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $guzzleResponse = $e->getResponse();
            } else {
                throw $e;
            }
        }

        return new StreamingResponse($guzzleResponse->getBody());
    }

    private function buildOptions(Request $request): array
    {
        $options = [
            RequestOptions::HEADERS => $request->headers,
        ];

        if ($request->body instanceof MultipartBody) {
            $options[RequestOptions::MULTIPART] = $request->body->parts;
        } elseif ($request->body !== null) {
            $options[RequestOptions::BODY] = $request->body;
        }

        return $options;
    }

    private function retryDecider(): callable
    {
        return function (
            int $retries,
            PsrRequestInterface $request,
            ?PsrResponseInterface $response = null,
            ?Throwable $exception = null
        ): bool {
            if ($retries >= $this->maxRetries) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // Store response for potential Retry-After header extraction
                $this->lastResponse = $response;

                if (in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES, true)) {
                    return true;
                }
            }

            return false;
        };
    }

    private function retryDelay(): callable
    {
        return function (int $numberOfRetries): int {
            // Check for Retry-After header (commonly sent with 429 responses)
            if ($this->lastResponse !== null) {
                $retryAfter = $this->lastResponse->getHeaderLine('Retry-After');

                if ($retryAfter !== '') {
                    // Retry-After can be seconds or HTTP-date
                    if (is_numeric($retryAfter)) {
                        return (int) $retryAfter * 1000; // Convert to milliseconds
                    }

                    // Try parsing as HTTP-date
                    $timestamp = strtotime($retryAfter);
                    if ($timestamp !== false) {
                        $delay = max(0, $timestamp - time());
                        return $delay * 1000; // Convert to milliseconds
                    }
                }
            }

            // Default exponential backoff: 1s, 2s, 4s, ...
            return 1000 * (int) pow(2, $numberOfRetries - 1);
        };
    }
}
