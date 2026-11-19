<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * Seconds to wait for each provider request. Defaults to 60.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Timeout
{
    /**
     * @param int $value
     */
    public function __construct(
        public readonly int $value
    ) {
    }
}
