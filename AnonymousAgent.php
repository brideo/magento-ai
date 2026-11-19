<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai;

use Stringable;
use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\ConversationalInterface;
use UpturnStudio\Ai\Api\HasToolsInterface;

/**
 * An agent defined inline, without a class of its own. Create one with the agent() function.
 */
class AnonymousAgent implements AgentInterface, ConversationalInterface, HasToolsInterface
{
    use Promptable;

    /**
     * @param Stringable|string $instructions
     * @param iterable $messages
     * @param iterable $tools
     */
    public function __construct(
        private readonly Stringable|string $instructions = '',
        private iterable $messages = [],
        private readonly iterable $tools = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    /**
     * @inheritDoc
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * Replace the agent's history.
     *
     * @param iterable $messages
     */
    public function withMessages(iterable $messages): static
    {
        $agent = clone $this;
        $agent->messages = $messages;

        return $agent;
    }
}
