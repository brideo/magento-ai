<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use UpturnStudio\Ai\Gateway\PendingStep;

/**
 * A generation step is about to call the model.
 */
class StartingStep
{
    /**
     * @param string $invocationId
     * @param PendingStep $step
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly PendingStep $step
    ) {
    }
}
