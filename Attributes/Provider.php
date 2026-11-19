<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;
use UpturnStudio\Ai\Enums\Lab;

/**
 * The provider an agent uses unless one is passed to prompt(). An array is a failover list.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Provider
{
    /**
     * @param Lab|array|string $value
     */
    public function __construct(
        public readonly Lab|array|string $value
    ) {
    }
}
