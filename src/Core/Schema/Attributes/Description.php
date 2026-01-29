<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Description
{
    public function __construct(public string $description)
    {
    }
}
