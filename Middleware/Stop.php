<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Middleware;

use Closure;
use UpturnStudio\Ai\Gateway\PendingStep;
use UpturnStudio\Ai\Gateway\StepResult;

/**
 * Step middleware that ends a run before the next model call when a condition holds.
 */
class Stop
{
    /**
     * @param Closure $condition
     * @param bool $stopWhen The condition result that stops the run.
     */
    private function __construct(
        private readonly Closure $condition,
        private readonly bool $stopWhen
    ) {
    }

    /**
     * Stop when the callback returns true.
     *
     * @param Closure $condition
     */
    public static function when(Closure $condition): self
    {
        return new self($condition, true);
    }

    /**
     * Stop unless the callback returns true.
     *
     * @param Closure $condition
     */
    public static function unless(Closure $condition): self
    {
        return new self($condition, false);
    }

    /**
     * Stop the run or pass the step on.
     *
     * @param PendingStep $step
     * @param Closure $next
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        if ((bool) ($this->condition)($step) === $this->stopWhen) {
            return StepResult::stop();
        }

        return StepResult::wrap($next($step));
    }
}
