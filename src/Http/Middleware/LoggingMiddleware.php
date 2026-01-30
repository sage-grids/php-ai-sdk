<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

/**
 * Middleware that logs HTTP requests and responses.
 *
 * Useful for debugging and monitoring API calls to AI providers.
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * @param LoggerInterface $logger PSR-3 compatible logger.
     * @param string $level Log level for messages (default: debug).
     * @param bool $logBody Whether to log request/response bodies (may contain sensitive data).
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = LogLevel::DEBUG,
        private readonly bool $logBody = false,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_', true);

        // Log request
        $requestContext = [
            'request_id' => $requestId,
            'method' => $request->method,
            'uri' => $request->uri,
            'headers' => $this->sanitizeHeaders($request->headers),
        ];

        if ($this->logBody && $request->body !== null) {
            $requestContext['body'] = $this->truncateBody((string) $request->body);
        }

        $this->logger->log($this->level, 'HTTP Request', $requestContext);

        // Execute request
        $response = $next($request);

        // Log response
        $duration = microtime(true) - $startTime;
        $responseContext = [
            'request_id' => $requestId,
            'status_code' => $response->statusCode,
            'duration_ms' => round($duration * 1000, 2),
        ];

        if ($this->logBody) {
            $responseContext['body'] = $this->truncateBody($response->body);
        }

        $this->logger->log($this->level, 'HTTP Response', $responseContext);

        return $response;
    }

    /**
     * Sanitize headers to hide sensitive information.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'x-api-key', 'api-key', 'x-goog-api-key'];

        $sanitized = [];
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            if (\in_array($lowerName, $sensitiveHeaders, true)) {
                $sanitized[$name] = '[REDACTED]';
            } else {
                $sanitized[$name] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Truncate body for logging.
     */
    private function truncateBody(string $body, int $maxLength = 1000): string
    {
        if (\strlen($body) <= $maxLength) {
            return $body;
        }

        return substr($body, 0, $maxLength) . '... [truncated]';
    }
}
