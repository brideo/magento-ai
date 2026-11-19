<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

use Stringable;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Events\Dispatcher;

/**
 * Base class for providers: holds the configuration and exposes the parts of it gateways need.
 */
abstract class Provider implements ProviderInterface, Stringable
{
    /**
     * @param array<string,mixed> $config
     * @param Dispatcher $events
     */
    public function __construct(
        protected readonly array $config,
        protected readonly Dispatcher $events
    ) {
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return (string) ($this->config['name'] ?? $this->driver());
    }

    /**
     * @inheritDoc
     */
    public function driver(): string
    {
        return (string) ($this->config['driver'] ?? '');
    }

    /**
     * @inheritDoc
     */
    public function credentials(): array
    {
        $key = $this->config['key'] ?? null;

        return ['key' => $key === null || $key === '' ? null : (string) $key];
    }

    /**
     * @inheritDoc
     */
    public function additionalConfiguration(): array
    {
        return array_diff_key($this->config, array_flip(['driver', 'key', 'name', 'ondemand']));
    }

    /**
     * The provider's name.
     */
    public function __toString(): string
    {
        return $this->name();
    }

    /**
     * A model name from the "models" configuration, e.g. modelFor('text', 'default').
     *
     * @param string $capability
     * @param string $kind
     * @param string $fallback
     */
    protected function modelFor(string $capability, string $kind, string $fallback): string
    {
        $model = $this->config['models'][$capability][$kind] ?? null;

        return is_string($model) && $model !== '' ? $model : $fallback;
    }
}
