<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use Closure;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Gateway\FakeTextGateway;
use UpturnStudio\Ai\Prompts\AgentPrompt;

/**
 * Resolves providers by name and builds them on demand. Inject this where static access through Ai is unwanted.
 */
interface AiManagerInterface
{
    /**
     * Create a provider at runtime from a configuration array, e.g. with a per-customer API key.
     *
     * @param array<string,mixed> $config Needs "driver"; takes "key", "url", "models", "headers" and "name".
     */
    public function build(array $config): ProviderInterface;

    /**
     * A provider by name. Null means the default text provider.
     *
     * @param Lab|ProviderInterface|string|null $name
     */
    public function provider(Lab|ProviderInterface|string|null $name = null): ProviderInterface;

    /**
     * A provider that can generate text.
     *
     * @param Lab|ProviderInterface|string|null $name
     */
    public function textProvider(Lab|ProviderInterface|string|null $name = null): TextProviderInterface;

    /**
     * The text provider for an agent, with the agent's fake gateway swapped in when it has one.
     *
     * @param AgentInterface $agent
     * @param string $name
     */
    public function textProviderFor(AgentInterface $agent, string $name): TextProviderInterface;

    /**
     * The name of the provider used when none is given.
     *
     * @param string $capability
     */
    public function defaultProviderName(string $capability = 'text'): string;

    /**
     * Fake an agent class: its prompts return the given responses instead of calling a provider.
     *
     * @param class-string $agentClass
     * @param Closure|array $responses
     */
    public function fakeAgent(string $agentClass, Closure|array $responses = []): FakeTextGateway;

    /**
     * Whether an agent class is faked.
     *
     * @param class-string $agentClass
     */
    public function hasFakeAgent(string $agentClass): bool;

    /**
     * Remember a prompt sent to a faked agent, for the assertions.
     *
     * @param AgentPrompt $prompt
     */
    public function recordPrompt(AgentPrompt $prompt): void;

    /**
     * The prompts recorded for an agent class.
     *
     * @param class-string $agentClass
     * @return AgentPrompt[]
     */
    public function recordedPrompts(string $agentClass): array;

    /**
     * Forget on-demand providers, fakes and recorded prompts. Call between queue messages and between tests.
     */
    public function flushState(): void;
}
