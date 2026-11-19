<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway;

use Closure;
use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Exceptions\StrayPromptException;
use UpturnStudio\Ai\Messages\UserMessage;
use UpturnStudio\Ai\Responses\Data\Meta;
use UpturnStudio\Ai\Schema\FakeData;

/**
 * The gateway installed by Agent::fake(). It answers from a list or a closure and never calls a provider.
 */
class FakeTextGateway implements TextGatewayInterface
{
    /** @var int */
    private int $index = 0;

    /** @var bool */
    private bool $preventStrayPrompts = false;

    /**
     * @param Closure|array $responses Strings, arrays (structured output), StepResponse objects or closures, used in
     *                                 order; or one closure receiving ($prompt, $messages, $provider, $model).
     */
    public function __construct(
        private readonly Closure|array $responses = []
    ) {
    }

    /**
     * Throw when a prompt arrives and no response is left, instead of answering with a placeholder.
     *
     * @param bool $prevent
     */
    public function preventStrayPrompts(bool $prevent = true): static
    {
        $this->preventStrayPrompts = $prevent;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function generateTextStep(
        TextProviderInterface $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options,
        ?int $timeout = null
    ): StepResponse {
        $prompt = '';
        foreach (array_reverse($messages) as $message) {
            if ($message instanceof UserMessage) {
                $prompt = $message->content;
                break;
            }
        }

        $response = $this->responses instanceof Closure
            ? ($this->responses)($prompt, $messages, $provider, $model)
            : ($this->responses[$this->index] ?? null);
        $this->index++;

        while ($response instanceof Closure) {
            $response = $response($prompt, $messages, $provider, $model);
        }

        if ($response === null) {
            if ($this->preventStrayPrompts) {
                throw new StrayPromptException(
                    sprintf('Attempted prompt [%s] without a fake agent response.', mb_substr($prompt, 0, 80))
                );
            }
            $response = $schema === null ? 'Fake response for prompt: ' . $prompt : FakeData::for($schema);
        }

        if (!$response instanceof StepResponse) {
            $response = is_array($response)
                ? new StepResponse(text: (string) json_encode($response), structured: $response)
                : new StepResponse(text: (string) $response);
        }

        if ($response->meta->provider === null) {
            $response->meta = new Meta($provider->name(), $model);
        }

        return $response;
    }
}
