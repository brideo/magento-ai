<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model;

use Closure;
use InvalidArgumentException;
use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\AiManagerInterface;
use UpturnStudio\Ai\Api\ConfigInterface;
use UpturnStudio\Ai\Api\Provider\DriverInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Gateway\FakeTextGateway;
use UpturnStudio\Ai\Model\Provider\FakeProvider;
use UpturnStudio\Ai\Prompts\AgentPrompt;

/**
 * Resolves named providers, builds on-demand ones and holds the fakes used in tests.
 *
 * Drivers are registered through the "drivers" di.xml argument, so other modules can add their own.
 */
class AiManager implements AiManagerInterface
{
    /** @var array<string, ProviderInterface> */
    private array $instances = [];

    /** @var array<string, array<string, mixed>> */
    private array $onDemandProviders = [];

    /** @var array<class-string, FakeTextGateway> */
    private array $fakeAgentGateways = [];

    /** @var array<class-string, AgentPrompt[]> */
    private array $recordedPrompts = [];

    /**
     * @param ConfigInterface $config
     * @param Dispatcher $events
     * @param array<string,DriverInterface> $drivers Keyed by driver name.
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly Dispatcher $events,
        private readonly array $drivers = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(array $config): ProviderInterface
    {
        if (empty($config['driver'])) {
            throw new InvalidArgumentException('An on-demand AI provider needs a "driver".');
        }

        $name = (string) ($config['name'] ?? 'ondemand_' . hash('sha256', (string) json_encode($config)));

        if (!str_starts_with($name, 'ondemand_')
            && (Lab::tryFrom($name) !== null || $this->config->providerConfig($name) !== null)
        ) {
            throw new InvalidArgumentException(sprintf('AI provider name [%s] is already taken.', $name));
        }

        $this->onDemandProviders[$name] = ['name' => $name, 'ondemand' => true] + $config;
        unset($this->instances[$name]);

        return $this->provider($name);
    }

    /**
     * @inheritDoc
     */
    public function provider(Lab|ProviderInterface|string|null $name = null): ProviderInterface
    {
        if ($name instanceof ProviderInterface) {
            return $name;
        }

        $name = $name instanceof Lab ? $name->value : ($name ?? $this->defaultProviderName());

        return $this->instances[$name] ??= $this->create($name);
    }

    /**
     * @inheritDoc
     */
    public function textProvider(Lab|ProviderInterface|string|null $name = null): TextProviderInterface
    {
        $provider = $this->provider($name);

        if (!$provider instanceof TextProviderInterface) {
            throw new AiException(sprintf('AI provider [%s] does not support text generation.', $provider->name()));
        }

        return $provider;
    }

    /**
     * @inheritDoc
     */
    public function textProviderFor(AgentInterface $agent, string $name): TextProviderInterface
    {
        $fake = $this->fakeAgentGateways[$agent::class] ?? null;

        if ($fake === null) {
            return $this->textProvider($name);
        }

        try {
            $provider = $this->textProvider($name);
        } catch (AiException | InvalidArgumentException) {
            // Faked agents must work with no provider configured at all.
            $provider = new FakeProvider(['name' => $name, 'driver' => $name], $this->events);
        }

        return $provider->useTextGateway($fake);
    }

    /**
     * @inheritDoc
     */
    public function defaultProviderName(string $capability = 'text'): string
    {
        return $this->config->defaultProvider($capability);
    }

    /**
     * @inheritDoc
     */
    public function fakeAgent(string $agentClass, Closure|array $responses = []): FakeTextGateway
    {
        $this->recordedPrompts[$agentClass] = [];

        return $this->fakeAgentGateways[$agentClass] = new FakeTextGateway($responses);
    }

    /**
     * @inheritDoc
     */
    public function hasFakeAgent(string $agentClass): bool
    {
        return isset($this->fakeAgentGateways[$agentClass]);
    }

    /**
     * @inheritDoc
     */
    public function recordPrompt(AgentPrompt $prompt): void
    {
        $this->recordedPrompts[$prompt->agent::class][] = $prompt;
    }

    /**
     * @inheritDoc
     */
    public function recordedPrompts(string $agentClass): array
    {
        return $this->recordedPrompts[$agentClass] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function flushState(): void
    {
        foreach (array_keys($this->onDemandProviders) as $name) {
            unset($this->instances[$name]);
        }

        $this->onDemandProviders = [];
        $this->fakeAgentGateways = [];
        $this->recordedPrompts = [];
    }

    /**
     * Create the provider registered under a name.
     *
     * @param string $name
     */
    private function create(string $name): ProviderInterface
    {
        $config = $this->onDemandProviders[$name]
            ?? $this->config->providerConfig($name)
            ?? (Lab::tryFrom($name) !== null ? ['driver' => $name] : null);

        if ($config === null) {
            throw new InvalidArgumentException(sprintf('AI provider [%s] is not configured.', $name));
        }

        $driver = (string) ($config['driver'] ?? $name);

        if (!isset($this->drivers[$driver])) {
            throw new InvalidArgumentException(sprintf('AI driver [%s] is not supported.', $driver));
        }

        return $this->drivers[$driver]->create(['name' => $name, 'driver' => $driver] + $config);
    }
}
