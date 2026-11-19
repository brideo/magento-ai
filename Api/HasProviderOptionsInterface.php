<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use UpturnStudio\Ai\Enums\Lab;

/**
 * An agent that adds provider-specific fields to the request body.
 */
interface HasProviderOptionsInterface
{
    /**
     * Extra request fields for the given provider. Useful with failover, where each provider needs its own.
     *
     * @param Lab|string $provider The driver as a Lab case when it is a built-in, otherwise the driver name.
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array;
}
