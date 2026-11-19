<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api\Provider;

/**
 * A configured, named provider. Capability interfaces extend this.
 */
interface ProviderInterface
{
    /**
     * The name the provider is registered under, e.g. "anthropic" or "ondemand_…".
     */
    public function name(): string;

    /**
     * The driver behind it, e.g. "anthropic" or "openai-compatible".
     */
    public function driver(): string;

    /**
     * The provider's credentials.
     *
     * @return array{key: ?string}
     */
    public function credentials(): array;

    /**
     * The rest of the configuration: url, headers, models and anything driver-specific.
     *
     * @return array<string, mixed>
     */
    public function additionalConfiguration(): array;
}
