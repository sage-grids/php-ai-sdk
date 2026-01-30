<?php

namespace Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Http\Middleware\MiddlewareInterface;
use SageGrids\PhpAiSdk\Http\Middleware\MiddlewarePipeline;
use SageGrids\PhpAiSdk\Http\Request;
use SageGrids\PhpAiSdk\Http\Response;

final class MiddlewarePipelineTest extends TestCase
{
    public function testExecuteWithNoMiddleware(): void
    {
        $pipeline = new MiddlewarePipeline();
        $request = new Request('GET', 'https://api.example.com/test');

        $response = $pipeline->execute($request, fn () => new Response(200, [], 'OK'));

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('OK', $response->body);
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $order = [];

        $middleware1 = $this->createMiddleware(function ($request, $next) use (&$order) {
            $order[] = 'before1';
            $response = $next($request);
            $order[] = 'after1';
            return $response;
        });

        $middleware2 = $this->createMiddleware(function ($request, $next) use (&$order) {
            $order[] = 'before2';
            $response = $next($request);
            $order[] = 'after2';
            return $response;
        });

        $pipeline = new MiddlewarePipeline();
        $pipeline->add($middleware1);
        $pipeline->add($middleware2);

        $request = new Request('GET', 'https://api.example.com/test');
        $pipeline->execute($request, function () use (&$order) {
            $order[] = 'handler';
            return new Response(200, [], 'OK');
        });

        $this->assertSame(['before1', 'before2', 'handler', 'after2', 'after1'], $order);
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $middleware = $this->createMiddleware(function ($request, $next) {
            $modifiedRequest = $request->withHeader('X-Custom', 'value');
            return $next($modifiedRequest);
        });

        $pipeline = new MiddlewarePipeline();
        $pipeline->add($middleware);

        $receivedRequest = null;
        $request = new Request('GET', 'https://api.example.com/test');
        $pipeline->execute($request, function ($req) use (&$receivedRequest) {
            $receivedRequest = $req;
            return new Response(200, [], 'OK');
        });

        $this->assertArrayHasKey('X-Custom', $receivedRequest->headers);
        $this->assertSame('value', $receivedRequest->headers['X-Custom']);
    }

    public function testMiddlewareCanModifyResponse(): void
    {
        $middleware = $this->createMiddleware(function ($request, $next) {
            $response = $next($request);
            return new Response($response->statusCode, ['X-Modified' => 'yes'], $response->body);
        });

        $pipeline = new MiddlewarePipeline();
        $pipeline->add($middleware);

        $request = new Request('GET', 'https://api.example.com/test');
        $response = $pipeline->execute($request, fn () => new Response(200, [], 'OK'));

        $this->assertArrayHasKey('X-Modified', $response->headers);
        $this->assertSame('yes', $response->headers['X-Modified']);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $handlerCalled = false;

        $middleware = $this->createMiddleware(function ($request, $next) {
            // Don't call $next, return early
            return new Response(401, [], 'Unauthorized');
        });

        $pipeline = new MiddlewarePipeline();
        $pipeline->add($middleware);

        $request = new Request('GET', 'https://api.example.com/test');
        $response = $pipeline->execute($request, function () use (&$handlerCalled) {
            $handlerCalled = true;
            return new Response(200, [], 'OK');
        });

        $this->assertFalse($handlerCalled);
        $this->assertSame(401, $response->statusCode);
    }

    public function testAddMany(): void
    {
        $middleware1 = $this->createMiddleware(fn ($r, $n) => $n($r));
        $middleware2 = $this->createMiddleware(fn ($r, $n) => $n($r));

        $pipeline = new MiddlewarePipeline();
        $pipeline->addMany([$middleware1, $middleware2]);

        $this->assertSame(2, $pipeline->count());
    }

    public function testIsEmpty(): void
    {
        $pipeline = new MiddlewarePipeline();
        $this->assertTrue($pipeline->isEmpty());

        $pipeline->add($this->createMiddleware(fn ($r, $n) => $n($r)));
        $this->assertFalse($pipeline->isEmpty());
    }

    private function createMiddleware(callable $handler): MiddlewareInterface
    {
        return new class ($handler) implements MiddlewareInterface {
            public function __construct(private readonly mixed $handler)
            {
            }

            public function handle(Request $request, callable $next): Response
            {
                return ($this->handler)($request, $next);
            }
        };
    }
}
