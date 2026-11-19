<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use Throwable;
use UpturnStudio\Ai\Api\AgentInterface;

/**
 * A provider failed and the agent run moved on to the next provider in its failover list.
 */
class AgentFailedOver
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
