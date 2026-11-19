<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use Throwable;
use UpturnStudio\Ai\Api\AgentInterface;

/**
 * An agent run failed and will not be retried on another provider.
 */
class AgentFailed
{
    /**
     * @param string $invocationId
     * @param AgentInterface $agent
     * @param string $provider
     * @param string $model
     * @param Throwable $exception
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentInterface $agent,
        public readonly string $provider,
        public readonly string $model,
        public readonly Throwable $exception
    ) {
    }
}
