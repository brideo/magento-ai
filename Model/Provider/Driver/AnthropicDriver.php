<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider\Driver;

use UpturnStudio\Ai\Api\Provider\DriverInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Provider\AnthropicProvider;

/**
 * Builds Anthropic providers.
 */
class AnthropicDriver implements DriverInterface
{
    /**
     * @param Client $http
     * @param Dispatcher $events
     */
    public function __construct(
        private readonly Client $http,
        private readonly Dispatcher $events
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(array $config): ProviderInterface
    {
        return new AnthropicProvider($config, $this->events, $this->http);
    }
}
