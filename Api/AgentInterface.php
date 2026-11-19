<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use Stringable;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Responses\AgentResponse;

/**
 * An agent: instructions plus, through the optional interfaces, history, tools and an output schema.
 *
 * Use the Promptable trait to get prompt() and the testing helpers.
 */
interface AgentInterface
{
    /**
     * The system prompt.
     */
    public function instructions(): Stringable|string;

    /**
     * Send a prompt and wait for the response.
     *
     * @param string $prompt
     * @param array<int,object> $attachments
     * @param Lab|ProviderInterface|array|string|null $provider A provider, or an array of them to fail over in order.
     * @param string|null $model
     * @param int|null $timeout Seconds per provider request.
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|ProviderInterface|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): AgentResponse;
}
