<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

use InvalidArgumentException;
use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Provider\Concerns\GeneratesText;

/**
 * Any endpoint that speaks OpenAI's chat completions format. It needs a "url"; a key is optional.
 */
class OpenAiCompatibleProvider extends Provider implements TextProviderInterface
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
        protected readonly Client $http
    ) {
        parent::__construct($config, $events);
    }

    /**
     * @inheritDoc
     */
    public function defaultTextModel(): string
    {
        $model = $this->modelFor('text', 'default', $this->fallbackTextModel());

        if ($model === '') {
            throw new InvalidArgumentException(sprintf(
                'The [%s] provider has no default text model. Configure one or pass a model to prompt().',
                $this->name()
            ));
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function cheapestTextModel(): string
    {
        return $this->modelFor('text', 'cheapest', $this->defaultTextModel());
    }

    /**
     * @inheritDoc
     */
    public function smartestTextModel(): string
    {
        return $this->modelFor('text', 'smartest', $this->defaultTextModel());
    }

    /**
     * The model used when none is configured. Empty means one must be configured.
     */
    protected function fallbackTextModel(): string
    {
        return '';
    }

    /**
     * The base URL used when none is configured. Null means one must be configured.
     */
    protected function defaultUrl(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    protected function createTextGateway(): TextGatewayInterface
    {
        return new OpenAiCompatibleGateway($this->http, $this->defaultUrl());
    }
}
