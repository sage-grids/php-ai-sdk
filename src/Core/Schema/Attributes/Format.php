<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Format
{
    public function __construct(public string $format)
    {
    }
}
