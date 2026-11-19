<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

/**
 * An agent with middleware that runs around every generation step.
 */
interface HasMiddlewareInterface
{
    /**
     * Middleware objects with handle(PendingStep $step, Closure $next), or closures of the same shape.
     *
     * @return array<int, object|callable>
     */
    public function middleware(): array;
}
