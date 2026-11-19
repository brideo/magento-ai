<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Gateway\Anthropic\AnthropicGateway;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Provider\Concerns\GeneratesText;

/**
 * Anthropic's Claude models through the Messages API.
 */
class AnthropicProvider extends Provider implements TextProviderInterface
{
    use GeneratesText;

    /**
     * @param array<string,mixed> $config
     * @param Dispatcher $events
     * @param Client $http
     */
    public function __construct(
        array $config,
        Dispatcher $events,
        private readonly Client $http
    ) {
        parent::__construct($config, $events);
    }

    /**
     * @inheritDoc
     */
    public function defaultTextModel(): string
    {
        return $this->modelFor('text', 'default', 'claude-opus-5-5');
    }

    /**
     * @inheritDoc
     */
    public function cheapestTextModel(): string
    {
        return $this->modelFor('text', 'cheapest', 'claude-haiku-5-5');
    }

    /**
     * @inheritDoc
     */
    public function smartestTextModel(): string
    {
        return $this->modelFor('text', 'smartest', 'claude-opus-5-5');
    }

    /**
     * @inheritDoc
     */
    protected function createTextGateway(): TextGatewayInterface
    {
        return new AnthropicGateway($this->http);
    }
}
