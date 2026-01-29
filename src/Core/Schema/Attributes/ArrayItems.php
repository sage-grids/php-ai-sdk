<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Schema\Attributes;

use Attribute;
use SageGrids\PhpAiSdk\Core\Schema\Schema;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class ArrayItems
{
    /**
     * @param string|Schema $items Class name string or Schema instance
     */
    public function __construct(
        public string|Schema $items,
        public ?int $minItems = null,
        public ?int $maxItems = null,
    ) {
    }
}
