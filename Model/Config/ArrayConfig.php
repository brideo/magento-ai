<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Config;

use UpturnStudio\Ai\Api\ConfigInterface;

/**
 * Provider configuration held in a plain array. Used outside Magento's config system, as in unit tests.
 */
class ArrayConfig implements ConfigInterface
{
    /**
     * @param array<string,array<string,mixed>> $providers Keyed by provider name.
     * @param array<string,string> $defaults Default provider per capability, e.g. ['text' => 'anthropic'].
     */
    public function __construct(
        private readonly array $providers = [],
        private readonly array $defaults = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function providerConfig(string $name): ?array
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function defaultProvider(string $capability = 'text'): string
    {
        return $this->defaults[$capability] ?? $this->defaults['text'] ?? 'anthropic';
    }
}
