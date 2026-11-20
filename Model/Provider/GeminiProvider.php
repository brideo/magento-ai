<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Gateway\Gemini\GeminiGateway;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Provider\Concerns\GeneratesText;

/**
 * Google Gemini models through the Interactions API.
 */
class GeminiProvider extends Provider implements TextProviderInterface
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
        return $this->modelFor('text', 'default', 'gemini-3.8-flash');
    }

    /**
     * @inheritDoc
     */
    public function cheapestTextModel(): string
    {
        return $this->modelFor('text', 'cheapest', 'gemini-3.1-flash-lite');
    }

    /**
     * @inheritDoc
     */
    public function smartestTextModel(): string
    {
        return $this->modelFor('text', 'smartest', 'gemini-3.8-flash');
    }

    /**
     * @inheritDoc
     */
    protected function createTextGateway(): TextGatewayInterface
    {
        return new GeminiGateway($this->http);
    }
}
