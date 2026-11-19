<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * The most model round-trips one run may take.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MaxSteps
{
    /**
     * @param int $value
     */
    public function __construct(
        public readonly int $value
    ) {
    }
}
