<?php

namespace Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SageGrids\PhpAiSdk\Http\Middleware\LoggingMiddleware;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

final class LoggingMiddlewareTest extends TestCase
{
    public function testLogsRequestAndResponse(): void
    {
        $logs = [];
        $logger = $this->createLogger($logs);

        $middleware = new LoggingMiddleware($logger);
        $request = new Request('POST', 'https://api.example.com/test', ['Content-Type' => 'application/json']);

        $middleware->handle($request, fn () => new Response(200, [], 'OK'));

        $this->assertCount(2, $logs);
        $this->assertSame('HTTP Request', $logs[0]['message']);
        $this->assertSame('HTTP Response', $logs[1]['message']);
    }

    public function testRedactsSensitiveHeaders(): void
    {
        $logs = [];
        $logger = $this->createLogger($logs);

        $middleware = new LoggingMiddleware($logger);
        $request = new Request('POST', 'https://api.example.com/test', [
            'Authorization' => 'Bearer secret-token',
            'X-API-Key' => 'my-api-key',
            'Content-Type' => 'application/json',
        ]);

        $middleware->handle($request, fn () => new Response(200, [], 'OK'));

        $headers = $logs[0]['context']['headers'];
        $this->assertSame('[REDACTED]', $headers['Authorization']);
        $this->assertSame('[REDACTED]', $headers['X-API-Key']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function testLogsBodyWhenEnabled(): void
    {
        $logs = [];
        $logger = $this->createLogger($logs);

        $middleware = new LoggingMiddleware($logger, LogLevel::DEBUG, logBody: true);
        $request = new Request('POST', 'https://api.example.com/test', [], '{"message": "hello"}');

        $middleware->handle($request, fn () => new Response(200, [], '{"response": "world"}'));

        $this->assertArrayHasKey('body', $logs[0]['context']);
        $this->assertSame('{"message": "hello"}', $logs[0]['context']['body']);
        $this->assertArrayHasKey('body', $logs[1]['context']);
        $this->assertSame('{"response": "world"}', $logs[1]['context']['body']);
    }

    public function testDoesNotLogBodyByDefault(): void
    {
        $logs = [];
        $logger = $this->createLogger($logs);

        $middleware = new LoggingMiddleware($logger);
        $request = new Request('POST', 'https://api.example.com/test', [], '{"message": "hello"}');

        $middleware->handle($request, fn () => new Response(200, [], '{"response": "world"}'));

        $this->assertArrayNotHasKey('body', $logs[0]['context']);
        $this->assertArrayNotHasKey('body', $logs[1]['context']);
    }

    public function testLogsDuration(): void
    {
        $logs = [];
        $logger = $this->createLogger($logs);

        $middleware = new LoggingMiddleware($logger);
        $request = new Request('GET', 'https://api.example.com/test');

        $middleware->handle($request, fn () => new Response(200, [], 'OK'));

        $this->assertArrayHasKey('duration_ms', $logs[1]['context']);
        $this->assertIsFloat($logs[1]['context']['duration_ms']);
    }

    public function testUsesCustomLogLevel(): void
    {
        $logs = [];
        $logger = $this->createLogger($logs);

        $middleware = new LoggingMiddleware($logger, LogLevel::INFO);
        $request = new Request('GET', 'https://api.example.com/test');

        $middleware->handle($request, fn () => new Response(200, [], 'OK'));

        $this->assertSame(LogLevel::INFO, $logs[0]['level']);
        $this->assertSame(LogLevel::INFO, $logs[1]['level']);
    }

    /**
     * @param array<int, array{level: string, message: string, context: array<string, mixed>}> $logs
     */
    private function createLogger(array &$logs): LoggerInterface
    {
        return new class ($logs) implements LoggerInterface {
            /** @param array<int, array{level: string, message: string, context: array<string, mixed>}> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->log('emergency', $message, $context);
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->log('alert', $message, $context);
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->log('critical', $message, $context);
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->log('error', $message, $context);
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->log('warning', $message, $context);
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->log('notice', $message, $context);
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->log('info', $message, $context);
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->log('debug', $message, $context);
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
