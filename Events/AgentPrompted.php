<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use UpturnStudio\Ai\Prompts\AgentPrompt;
use UpturnStudio\Ai\Responses\AgentResponse;

/**
 * An agent finished a prompt. Listen here to record usage.
 */
class AgentPrompted
{
    /**
     * @param string $invocationId
     * @param AgentPrompt $prompt
     * @param AgentResponse $response
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentPrompt $prompt,
        public readonly AgentResponse $response
    ) {
    }
}
