<?php

namespace SageGrids\PhpAiSdk\Core\Tool\Attributes;

use Attribute;

/**
 * Provides metadata for a tool parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Parameter
{
    /**
     * @param string|null $description Description of the parameter for the AI model.
     * @param bool $optional Whether the parameter is optional.
     */
    public function __construct(
        public ?string $description = null,
        public bool $optional = false,
    ) {
    }
}
