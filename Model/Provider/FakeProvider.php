<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Gateway\FakeTextGateway;
use UpturnStudio\Ai\Model\Provider\Concerns\GeneratesText;

/**
 * Stands in for a provider that is not configured while an agent is faked, so tests need no provider setup.
 */
class FakeProvider extends Provider implements TextProviderInterface
{
    use GeneratesText;

    /**
     * @inheritDoc
     */
    public function defaultTextModel(): string
    {
        return $this->modelFor('text', 'default', 'fake-model');
    }

    /**
     * @inheritDoc
     */
    public function cheapestTextModel(): string
    {
        return $this->modelFor('text', 'cheapest', 'fake-cheapest-model');
    }

    /**
     * @inheritDoc
     */
    public function smartestTextModel(): string
    {
        return $this->modelFor('text', 'smartest', 'fake-smartest-model');
    }

    /**
     * @inheritDoc
     */
    protected function createTextGateway(): TextGatewayInterface
    {
        return new FakeTextGateway();
    }
}
