<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Responses\AgentResponse;

/**
 * Runs an agent: resolves its provider and model, fails over between providers and returns the response.
 *
 * Promptable::prompt() delegates here. Inject it to run agents without the static Ai locator.
 */
interface AgentRunnerInterface
{
    /**
     * Send a prompt to an agent and wait for the response.
     *
     * @param AgentInterface $agent
     * @param string $prompt
     * @param array<int,object> $attachments
     * @param Lab|ProviderInterface|array|string|null $provider
     * @param string|null $model
     * @param int|null $timeout
     * @param Message[] $messages History for this run only, for agents that are not conversational.
     * @param array<int,object>|null $tools Replaces the agent's tools for this run when not null.
     */
    public function prompt(
        AgentInterface $agent,
        string $prompt,
        array $attachments = [],
        Lab|ProviderInterface|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
        array $messages = [],
        ?array $tools = null
    ): AgentResponse;
}
