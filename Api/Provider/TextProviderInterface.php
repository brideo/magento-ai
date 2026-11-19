<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api\Provider;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Prompts\AgentPrompt;
use UpturnStudio\Ai\Responses\AgentResponse;

/**
 * A provider that generates text.
 */
interface TextProviderInterface extends ProviderInterface
{
    /**
     * Run an agent prompt to completion, including its tool loop.
     *
     * @param AgentPrompt $prompt
     */
    public function prompt(AgentPrompt $prompt): AgentResponse;

    /**
     * The gateway that talks to the provider's API.
     */
    public function textGateway(): TextGatewayInterface;

    /**
     * A copy of the provider that uses another gateway. This is how fakes are installed.
     *
     * @param TextGatewayInterface $gateway
     */
    public function useTextGateway(TextGatewayInterface $gateway): static;

    /**
     * The model used when none is given.
     */
    public function defaultTextModel(): string;

    /**
     * The model #[UseCheapestModel] picks.
     */
    public function cheapestTextModel(): string;

    /**
     * The model #[UseSmartestModel] picks.
     */
    public function smartestTextModel(): string;
}
