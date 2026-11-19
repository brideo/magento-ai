<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Prompts;

use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Messages\Message;

/**
 * One prompt on its way to one provider. Events and fake assertions receive it.
 */
class AgentPrompt
{
    /**
     * @param AgentInterface $agent
     * @param string $prompt
     * @param array<int,object> $attachments
     * @param TextProviderInterface $provider
     * @param string $model
     * @param int $timeout
     * @param string $invocationId
     * @param Message[] $messages History passed for this run only.
     * @param array<int,object>|null $tools Runtime replacement for the agent's tools.
     */
    public function __construct(
        public readonly AgentInterface $agent,
        public readonly string $prompt,
        public readonly array $attachments,
        public readonly TextProviderInterface $provider,
        public readonly string $model,
        public readonly int $timeout = 60,
        public readonly string $invocationId = '',
        public readonly array $messages = [],
        public readonly ?array $tools = null
    ) {
    }

    /**
     * Whether the prompt text contains a string.
     *
     * @param string $needle
     */
    public function contains(string $needle): bool
    {
        return str_contains($this->prompt, $needle);
    }
}
