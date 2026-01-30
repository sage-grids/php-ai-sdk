<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http\Middleware;

use Psr\SimpleCache\CacheInterface;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

/**
 * Middleware that caches HTTP responses.
 *
 * Caches responses based on request hash to avoid redundant API calls.
 * Useful for development/testing or caching deterministic requests.
 */
final class CachingMiddleware implements MiddlewareInterface
{
    /**
     * @param CacheInterface $cache PSR-16 compatible cache.
     * @param int $ttl Cache TTL in seconds (default: 3600 = 1 hour).
     * @param string $prefix Cache key prefix.
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
        private readonly string $prefix = 'ai_sdk_cache_',
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        // Only cache GET and POST requests (POST for AI APIs)
        if (!\in_array($request->method, ['GET', 'POST'], true)) {
            return $next($request);
        }

        $cacheKey = $this->generateCacheKey($request);

        // Try to get from cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && \is_array($cached)) {
            return new Response(
                statusCode: (int) ($cached['statusCode'] ?? 200),
                headers: (array) ($cached['headers'] ?? []),
                body: (string) ($cached['body'] ?? ''),
            );
        }

        // Execute request
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccess()) {
            $this->cache->set($cacheKey, [
                'statusCode' => $response->statusCode,
                'headers' => $response->headers,
                'body' => $response->body,
            ], $this->ttl);
        }

        return $response;
    }

    /**
     * Generate a cache key from the request.
     */
    private function generateCacheKey(Request $request): string
    {
        $data = [
            'method' => $request->method,
            'uri' => $request->uri,
            'body' => $request->body,
        ];

        return $this->prefix . hash('sha256', json_encode($data) ?: '');
    }
}
