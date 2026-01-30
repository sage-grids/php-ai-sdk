<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http\Middleware;

/**
 * Trait for adding middleware support to providers.
 *
 * Use this trait in provider classes to add middleware capabilities.
 */
trait HasMiddleware
{
    /** @var MiddlewareInterface[] */
    protected array $middleware = [];

    /**
     * Add middleware to this provider.
     *
     * @return $this
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Add multiple middleware to this provider.
     *
     * @param MiddlewareInterface[] $middleware
     * @return $this
     */
    public function addMiddlewareMany(array $middleware): self
    {
        foreach ($middleware as $mw) {
            $this->addMiddleware($mw);
        }
        return $this;
    }

    /**
     * Get the middleware pipeline.
     */
    protected function getMiddlewarePipeline(): MiddlewarePipeline
    {
        $pipeline = new MiddlewarePipeline();
        $pipeline->addMany($this->middleware);
        return $pipeline;
    }

    /**
     * Check if middleware is configured.
     */
    protected function hasMiddleware(): bool
    {
        return !empty($this->middleware);
    }
}
