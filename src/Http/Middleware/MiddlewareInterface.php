<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http\Middleware;

use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

/**
 * Interface for HTTP middleware.
 *
 * Middleware can intercept and modify HTTP requests and responses,
 * enabling cross-cutting concerns like logging, caching, rate limiting,
 * and authentication handling.
 */
interface MiddlewareInterface
{
    /**
     * Handle the HTTP request.
     *
     * @param Request $request The HTTP request.
     * @param callable(Request): Response $next The next middleware or HTTP client.
     * @return Response The HTTP response.
     */
    public function handle(Request $request, callable $next): Response;
}
