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

/**
 * Builds providers of one class. Each built-in driver is a di.xml virtual type of this with its provider class.
 *
 * The provider class must take ($config, Dispatcher $events, Client $http) in its constructor.
 */
class ProviderDriver implements DriverInterface
{
    /**
     * @param Client $http
     * @param Dispatcher $events
     * @param string $providerClass
     */
    public function __construct(
        private readonly Client $http,
        private readonly Dispatcher $events,
        private readonly string $providerClass
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(array $config): ProviderInterface
    {
        return new ($this->providerClass)($config, $this->events, $this->http);
    }
}
