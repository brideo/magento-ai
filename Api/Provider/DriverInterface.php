<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api\Provider;

/**
 * Builds providers for one driver name. Register drivers on AiManager's "drivers" argument in di.xml.
 */
interface DriverInterface
{
    /**
     * Create a provider from its configuration.
     *
     * @param array<string,mixed> $config Includes "name" and "driver".
     */
    public function create(array $config): ProviderInterface;
}
