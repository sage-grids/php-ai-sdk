<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Http;

class MultipartBody
{
    /**
     * @param array $parts Array of parts. Each part is an associative array with keys:
     *                     - name: (string)
     *                     - contents: (string|resource)
     *                     - filename: (string|null)
     *                     - headers: (array|null)
     */
    public function __construct(
        public readonly array $parts = []
    ) {
    }
}
