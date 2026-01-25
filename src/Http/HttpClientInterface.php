<?php

namespace SageGrids\PhpAiSdk\Http;

interface HttpClientInterface
{
    public function request(Request $request): Response;

    public function stream(Request $request): StreamingResponse;
}
