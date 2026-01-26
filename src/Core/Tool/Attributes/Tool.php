<?php

namespace SageGrids\PhpAiSdk\Core\Tool\Attributes;

use Attribute;

/**
 * Marks a method as a tool that can be called by the AI model.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Tool
{
    /**
     * @param string|null $name Custom tool name. If null, method name is used.
     * @param string|null $description Tool description for the AI model.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
