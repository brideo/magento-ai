<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * The model an agent uses unless one is passed to prompt().
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Model
{
    /**
     * @param string $value
     */
    public function __construct(
        public readonly string $value
    ) {
    }
}
