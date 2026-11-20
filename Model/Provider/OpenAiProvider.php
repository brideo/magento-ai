<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Gateway\OpenAi\OpenAiGateway;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Provider\Concerns\GeneratesText;

/**
 * OpenAI models through the Responses API.
 */
class OpenAiProvider extends Provider implements TextProviderInterface
{
    use GeneratesText;

    /**
     * @param array $config
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
        return $this->modelFor('text', 'default', 'gpt-6.1-sol');
    }

    /**
     * @inheritDoc
     */
    public function cheapestTextModel(): string
    {
        return $this->modelFor('text', 'cheapest', 'gpt-6-luna');
    }

    /**
     * @inheritDoc
     */
    public function smartestTextModel(): string
    {
        return $this->modelFor('text', 'smartest', 'gpt-6-astra');
    }

    /**
     * @inheritDoc
     */
    protected function createTextGateway(): TextGatewayInterface
    {
        return new OpenAiGateway($this->http);
    }
}
