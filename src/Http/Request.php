<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http;

class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers = [],
        public readonly mixed $body = null,
    ) {
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->method, $this->uri, $headers, $this->body);
    }
}
