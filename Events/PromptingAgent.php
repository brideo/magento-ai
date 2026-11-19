<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use UpturnStudio\Ai\Prompts\AgentPrompt;

/**
 * An agent is about to be prompted.
 */
class PromptingAgent
{
    /**
     * @param string $invocationId
     * @param AgentPrompt $prompt
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentPrompt $prompt
    ) {
    }
}
