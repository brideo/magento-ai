<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Attributes;

use Attribute;

/**
 * Place a prompt-cache breakpoint after the agent's instructions.
 *
 * Providers without explicit cache breakpoints ignore it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class CacheInstructions
{
    /**
     * @param string $value Cache lifetime: "5m" (default) or "1h".
     */
    public function __construct(
        public readonly string $value = '5m'
    ) {
    }
}
