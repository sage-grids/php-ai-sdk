<?php

declare(strict_types=1);

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

    /**
     * Send a streaming request to the API.
     *
     * Includes retry logic ONLY for pre-data connection failures (e.g., DNS errors,
     * connection timeouts, refused connections). Once any response data has been
     * received, retries are disabled to prevent data corruption/duplication.
     */
    public function stream(Request $request): StreamingResponse
    {
        $options = $this->buildOptions($request);
        $options[RequestOptions::STREAM] = true;

        $retryCount = 0;
        $lastException = null;

        while ($retryCount <= $this->maxRetries) {
            try {
                // Use streamClient WITHOUT retry middleware to prevent data corruption
                // Once streaming starts, retrying would duplicate or corrupt data
                $guzzleResponse = $this->streamClient->request(
                    $request->method,
                    $request->uri,
                    $options
                );

                return new StreamingResponse($guzzleResponse->getBody());
            } catch (RequestException $e) {
                // If we have a response, return it (even for error status codes)
                if ($e->hasResponse()) {
                    return new StreamingResponse($e->getResponse()->getBody());
                }

                // Check if this is a retryable connection error (pre-data)
                if ($this->isRetryableConnectionError($e) && $retryCount < $this->maxRetries) {
                    $lastException = $e;
                    $retryCount++;

                    // Exponential backoff: 1s, 2s, 4s, ...
                    $delayMs = 1000 * (int) pow(2, $retryCount - 1);
                    usleep($delayMs * 1000);
                    continue;
                }

                throw $e;
            }
        }

        // Should not reach here, but throw last exception if we do
        throw $lastException ?? new \RuntimeException('Stream request failed after retries');
    }

    /**
     * Determine if a request exception represents a retryable connection error.
     *
     * Only connection errors that occur BEFORE any data transfer are retryable.
     * This includes DNS failures, connection refused, connection timeout, etc.
     */
    private function isRetryableConnectionError(RequestException $e): bool
    {
        // ConnectException specifically represents connection failures
        // before any data transfer (DNS, connection refused, timeout on connect)
        if ($e instanceof ConnectException) {
            return true;
        }

        // If there's a response, data was received - not retryable
        if ($e->hasResponse()) {
            return false;
        }

        // Check for common connection error messages
        $message = strtolower($e->getMessage());
        $connectionErrors = [
            'connection refused',
            'connection timed out',
            'could not resolve host',
            'name or service not known',
            'network is unreachable',
            'no route to host',
        ];

        foreach ($connectionErrors as $errorPattern) {
            if (str_contains($message, $errorPattern)) {
                return true;
            }
        }

        return false;
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
