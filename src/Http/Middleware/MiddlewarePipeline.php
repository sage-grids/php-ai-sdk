<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http\Middleware;

use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

/**
 * Pipeline for executing middleware in order.
 *
 * Builds a chain of middleware handlers that process requests in order,
 * with responses flowing back through in reverse order.
 */
final class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    /**
     * Add middleware to the pipeline.
     *
     * @return $this
     */
    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Add multiple middleware to the pipeline.
     *
     * @param MiddlewareInterface[] $middleware
     * @return $this
     */
    public function addMany(array $middleware): self
    {
        foreach ($middleware as $mw) {
            $this->add($mw);
        }
        return $this;
    }

    /**
     * Execute the pipeline with a final handler.
     *
     * @param Request $request The HTTP request.
     * @param callable(Request): Response $handler The final handler (usually HTTP client).
     * @return Response The HTTP response.
     */
    public function execute(Request $request, callable $handler): Response
    {
        // Build the middleware chain from the inside out
        // The last middleware added executes first, wrapping around the handler
        $pipeline = $handler;

        foreach (array_reverse($this->middleware) as $mw) {
            $pipeline = fn (Request $req): Response => $mw->handle($req, $pipeline);
        }

        return $pipeline($request);
    }

    /**
     * Check if the pipeline has any middleware.
     */
    public function isEmpty(): bool
    {
        return empty($this->middleware);
    }

    /**
     * Get the number of middleware in the pipeline.
     */
    public function count(): int
    {
        return \count($this->middleware);
    }
}
