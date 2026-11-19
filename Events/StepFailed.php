<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use Throwable;

/**
 * A generation step threw.
 */
class StepFailed
{
    /**
     * @param string $invocationId
     * @param int $number
     * @param Throwable $exception
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly int $number,
        public readonly Throwable $exception
    ) {
    }
}
