<?php

namespace SageGrids\PhpAiSdk\Core\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Minimum
{
    public function __construct(public int|float $minimum) {}
}
