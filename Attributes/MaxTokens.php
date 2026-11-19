<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * The most tokens the model may generate per step.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MaxTokens
{
    /**
     * @param int $value
     */
    public function __construct(
        public readonly int $value
    ) {
    }
}
