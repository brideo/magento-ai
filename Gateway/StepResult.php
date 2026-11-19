<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway;

use Closure;
use LogicException;

/**
 * What step middleware returns: the model's response, or an instruction to stop the run.
 */
class StepResult
{
    /**
     * @param StepResponse|null $response Null when the run was stopped before the model was called.
     */
    public function __construct(
        private ?StepResponse $response
    ) {
    }

    /**
     * End the run before the next model call. The response keeps the steps already completed.
     */
    public static function stop(): self
    {
        return new self(null);
    }

    /**
     * Normalise a middleware return value.
     *
     * @param mixed $value
     * @throws LogicException When middleware returned something else.
     */
    public static function wrap(mixed $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            $value instanceof StepResponse => new self($value),
            default => throw new LogicException(
                'Agent middleware must return $next($step), a StepResponse or StepResult::stop().'
            ),
        };
    }

    /**
     * Run a callback with the model's response, after it answers and before its tools run.
     *
     * Returning a StepResponse from the callback replaces the response.
     *
     * @param Closure $callback
     */
    public function then(Closure $callback): self
    {
        if ($this->response !== null) {
            $replacement = $callback($this->response);
            if ($replacement instanceof StepResponse) {
                $this->response = $replacement;
            }
        }

        return $this;
    }

    /**
     * Whether the run was stopped.
     */
    public function isStopped(): bool
    {
        return $this->response === null;
    }

    /**
     * The model's response, or null when the run was stopped.
     */
    public function response(): ?StepResponse
    {
        return $this->response;
    }
}
