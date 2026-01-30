<?php

namespace Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use SageGrids\PhpAiSdk\Http\Middleware\CachingMiddleware;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

final class CachingMiddlewareTest extends TestCase
{
    public function testReturnsCachedResponse(): void
    {
        $cache = $this->createCache([
            $this->getCacheKey('POST', 'https://api.example.com/test', '{"prompt":"hello"}') => [
                'statusCode' => 200,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => '{"cached": true}',
            ],
        ]);

        $handlerCalled = false;
        $middleware = new CachingMiddleware($cache);
        $request = new Request('POST', 'https://api.example.com/test', [], '{"prompt":"hello"}');

        $response = $middleware->handle($request, function () use (&$handlerCalled) {
            $handlerCalled = true;
            return new Response(200, [], '{"fresh": true}');
        });

        $this->assertFalse($handlerCalled);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('{"cached": true}', $response->body);
    }

    public function testCachesSuccessfulResponse(): void
    {
        $storedData = [];
        $cache = $this->createCache([], $storedData);

        $middleware = new CachingMiddleware($cache);
        $request = new Request('POST', 'https://api.example.com/test', [], '{"prompt":"hello"}');

        $response = $middleware->handle($request, fn () => new Response(200, ['X-Custom' => 'header'], '{"response": "world"}'));

        $this->assertCount(1, $storedData);
        $cachedEntry = array_values($storedData)[0];
        $this->assertSame(200, $cachedEntry['data']['statusCode']);
        $this->assertSame('{"response": "world"}', $cachedEntry['data']['body']);
    }

    public function testDoesNotCacheFailedResponse(): void
    {
        $storedData = [];
        $cache = $this->createCache([], $storedData);

        $middleware = new CachingMiddleware($cache);
        $request = new Request('POST', 'https://api.example.com/test', [], '{"prompt":"hello"}');

        $middleware->handle($request, fn () => new Response(500, [], 'Error'));

        $this->assertEmpty($storedData);
    }

    public function testSkipsNonGetPostRequests(): void
    {
        $storedData = [];
        $cache = $this->createCache([], $storedData);

        $middleware = new CachingMiddleware($cache);
        $request = new Request('DELETE', 'https://api.example.com/test');

        $handlerCalled = false;
        $middleware->handle($request, function () use (&$handlerCalled) {
            $handlerCalled = true;
            return new Response(204, [], '');
        });

        $this->assertTrue($handlerCalled);
        $this->assertEmpty($storedData);
    }

    public function testUsesCustomTtl(): void
    {
        $storedData = [];
        $cache = $this->createCache([], $storedData);

        $middleware = new CachingMiddleware($cache, ttl: 7200);
        $request = new Request('POST', 'https://api.example.com/test', [], '{"test":1}');

        $middleware->handle($request, fn () => new Response(200, [], 'OK'));

        $this->assertSame(7200, array_values($storedData)[0]['ttl']);
    }

    public function testUsesCustomPrefix(): void
    {
        $storedData = [];
        $cache = $this->createCache([], $storedData);

        $middleware = new CachingMiddleware($cache, prefix: 'my_prefix_');
        $request = new Request('POST', 'https://api.example.com/test', [], '{"test":1}');

        $middleware->handle($request, fn () => new Response(200, [], 'OK'));

        $key = array_keys($storedData)[0];
        $this->assertStringStartsWith('my_prefix_', $key);
    }

    private function getCacheKey(string $method, string $uri, ?string $body): string
    {
        $data = [
            'method' => $method,
            'uri' => $uri,
            'body' => $body,
        ];

        return 'ai_sdk_cache_' . hash('sha256', json_encode($data) ?: '');
    }

    /**
     * @param array<string, mixed> $initialData
     * @param array<string, array{data: mixed, ttl: int|null}> $storedData
     */
    private function createCache(array $initialData, array &$storedData = []): CacheInterface
    {
        return new class ($initialData, $storedData) implements CacheInterface {
            /** @param array<string, mixed> $initialData */
            public function __construct(
                private readonly array $initialData,
                private array &$storedData
            ) {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->initialData[$key] ?? $default;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->storedData[$key] = [
                    'data' => $value,
                    'ttl' => $ttl,
                ];
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->initialData[$key]);
            }
        };
    }
}
