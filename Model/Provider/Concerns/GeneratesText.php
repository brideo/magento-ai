<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider\Concerns;

use UpturnStudio\Ai\Api\ConversationalInterface;
use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\HasStructuredOutputInterface;
use UpturnStudio\Ai\Api\HasToolsInterface;
use UpturnStudio\Ai\Events\AgentPrompted;
use UpturnStudio\Ai\Events\PromptingAgent;
use UpturnStudio\Ai\Gateway\TextGenerationLoop;
use UpturnStudio\Ai\Gateway\TextGenerationOptions;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Messages\UserMessage;
use UpturnStudio\Ai\Prompts\AgentPrompt;
use UpturnStudio\Ai\Responses\AgentResponse;
use UpturnStudio\Ai\Responses\StructuredAgentResponse;
use UpturnStudio\Ai\Schema\JsonSchema;

/**
 * Text generation for a provider: turns an agent prompt into messages, tools and a schema, and runs the loop.
 */
trait GeneratesText
{
    /** @var TextGatewayInterface|null */
    private ?TextGatewayInterface $textGateway = null;

    /**
     * Run an agent prompt to completion.
     *
     * @param AgentPrompt $prompt
     */
    public function prompt(AgentPrompt $prompt): AgentResponse
    {
        $agent = $prompt->agent;

        $this->events->dispatch(new PromptingAgent($prompt->invocationId, $prompt));

        $history = $prompt->messages;
        if ($history === [] && $agent instanceof ConversationalInterface) {
            $history = [...$agent->messages()];
        }

        $messages = array_map(static fn (Message|array $message) => Message::from($message), array_values($history));
        $messages[] = new UserMessage($prompt->prompt, $prompt->attachments);

        $tools = $prompt->tools ?? ($agent instanceof HasToolsInterface ? [...$agent->tools()] : []);

        $schema = $agent instanceof HasStructuredOutputInterface
            ? JsonSchema::fromProperties($agent->schema(new JsonSchema()))
            : null;

        $result = (new TextGenerationLoop($this->textGateway(), $this->events))->generate(
            $this,
            $prompt->model,
            (string) $agent->instructions() === '' ? null : (string) $agent->instructions(),
            $messages,
            array_values($tools),
            $schema,
            TextGenerationOptions::forAgent($agent, $this),
            $prompt->timeout,
            $agent,
            $prompt->invocationId
        );

        $response = $schema === null
            ? new AgentResponse($prompt->invocationId, $result)
            : new StructuredAgentResponse($prompt->invocationId, $result);

        $this->events->dispatch(new AgentPrompted($prompt->invocationId, $prompt, $response));

        return $response;
    }

    /**
     * The gateway that talks to the provider's API.
     */
    public function textGateway(): TextGatewayInterface
    {
        return $this->textGateway ??= $this->createTextGateway();
    }

    /**
     * A copy of the provider that uses another gateway.
     *
     * @param TextGatewayInterface $gateway
     */
    public function useTextGateway(TextGatewayInterface $gateway): static
    {
        $provider = clone $this;
        $provider->textGateway = $gateway;

        return $provider;
    }

    /**
     * Create the provider's real gateway.
     */
    abstract protected function createTextGateway(): TextGatewayInterface;
}
