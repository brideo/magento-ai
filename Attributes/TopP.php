<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * Nucleus sampling. Providers that no longer accept it ignore it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TopP
{
    /**
     * @param float $value
     */
    public function __construct(
        public readonly float $value
    ) {
    }
}
