<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use UpturnStudio\Ai\Responses\Data\Step;

/**
 * A generation step finished, including its tool calls.
 */
class StepCompleted
{
    /**
     * @param string $invocationId
     * @param int $number
     * @param Step $step
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly int $number,
        public readonly Step $step
    ) {
    }
}
