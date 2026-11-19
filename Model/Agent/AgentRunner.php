<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Agent;

use Throwable;
use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\AgentRunnerInterface;
use UpturnStudio\Ai\Api\AiManagerInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Attributes\Model;
use UpturnStudio\Ai\Attributes\Provider;
use UpturnStudio\Ai\Attributes\Timeout;
use UpturnStudio\Ai\Attributes\UseCheapestModel;
use UpturnStudio\Ai\Attributes\UseSmartestModel;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Events\AgentFailed;
use UpturnStudio\Ai\Events\AgentFailedOver;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Exceptions\FailoverableException;
use UpturnStudio\Ai\Prompts\AgentPrompt;
use UpturnStudio\Ai\Responses\AgentResponse;

/**
 * Runs agents: works out which providers and models to use, and fails over between them.
 */
class AgentRunner implements AgentRunnerInterface
{
    private const DEFAULT_TIMEOUT = 60;

    /**
     * @param AiManagerInterface $manager
     * @param Dispatcher $events
     */
    public function __construct(
        private readonly AiManagerInterface $manager,
        private readonly Dispatcher $events
    ) {
    }

    /**
     * @inheritDoc
     */
    public function prompt(
        AgentInterface $agent,
        string $prompt,
        array $attachments = [],
        Lab|ProviderInterface|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
        array $messages = [],
        ?array $tools = null
    ): AgentResponse {
        $invocationId = self::uuid();
        $candidates = $this->providersAndModels($agent, $provider, $model);
        $timeout ??= (int) (AttributeReader::value($agent, Timeout::class, 'timeout') ?? self::DEFAULT_TIMEOUT);
        $remaining = count($candidates);

        foreach ($candidates as $name => $candidateModel) {
            $remaining--;
            $textProvider = $this->manager->textProviderFor($agent, (string) $name);
            $resolvedModel = $candidateModel ?? $this->defaultModelFor($agent, $textProvider);

            $agentPrompt = new AgentPrompt(
                $agent,
                $prompt,
                $attachments,
                $textProvider,
                $resolvedModel,
                $timeout,
                $invocationId,
                $messages,
                $tools
            );

            if ($this->manager->hasFakeAgent($agent::class)) {
                $this->manager->recordPrompt($agentPrompt);
            }

            try {
                return $textProvider->prompt($agentPrompt);
            } catch (FailoverableException $e) {
                if ($remaining === 0) {
                    $this->failed($invocationId, $agent, $textProvider, $resolvedModel, $e);
                    throw $e;
                }

                $this->events->dispatch(
                    new AgentFailedOver($invocationId, $agent, $textProvider->name(), $resolvedModel, $e)
                );
            } catch (Throwable $e) {
                $this->failed($invocationId, $agent, $textProvider, $resolvedModel, $e);
                throw $e;
            }
        }

        // Unreachable: the list always holds at least one provider, and the last failure is rethrown.
        throw new \LogicException('No AI provider was available for the agent.');
    }

    /**
     * The providers to try, in order, each with the model to use or null for the provider's default.
     *
     * Resolution order: the prompt() argument, the agent's provider() method, its #[Provider] attribute, then
     * the configured default. An explicit model applies only when a single provider is in play.
     *
     * @param AgentInterface $agent
     * @param Lab|ProviderInterface|array|string|null $provider
     * @param string|null $model
     * @return array<string, ?string>
     */
    public function providersAndModels(
        AgentInterface $agent,
        Lab|ProviderInterface|array|string|null $provider,
        ?string $model
    ): array {
        $provider ??= AttributeReader::value($agent, Provider::class, 'provider')
            ?? $this->manager->defaultProviderName();

        if (!is_array($provider)) {
            $model ??= AttributeReader::value($agent, Model::class, 'model');

            return [$this->providerName($provider) => $model];
        }

        $list = [];
        foreach ($provider as $key => $value) {
            if (is_int($key)) {
                $list[$this->providerName($value)] = null;
            } else {
                $list[$key] = $value === null ? null : (string) $value;
            }
        }

        return $list;
    }

    /**
     * The registered name of a provider given as an object, enum case or string.
     *
     * @param Lab|ProviderInterface|string $provider
     */
    private function providerName(Lab|ProviderInterface|string $provider): string
    {
        return match (true) {
            $provider instanceof ProviderInterface => $provider->name(),
            $provider instanceof Lab => $provider->value,
            default => $provider,
        };
    }

    /**
     * The model for an agent on a provider when none was given.
     *
     * @param AgentInterface $agent
     * @param TextProviderInterface $provider
     */
    private function defaultModelFor(AgentInterface $agent, TextProviderInterface $provider): string
    {
        return match (true) {
            AttributeReader::has($agent, UseSmartestModel::class) => $provider->smartestTextModel(),
            AttributeReader::has($agent, UseCheapestModel::class) => $provider->cheapestTextModel(),
            default => $provider->defaultTextModel(),
        };
    }

    /**
     * Announce a run that failed for good.
     *
     * @param string $invocationId
     * @param AgentInterface $agent
     * @param TextProviderInterface $provider
     * @param string $model
     * @param Throwable $e
     */
    private function failed(
        string $invocationId,
        AgentInterface $agent,
        TextProviderInterface $provider,
        string $model,
        Throwable $e
    ): void {
        $this->events->dispatch(new AgentFailed($invocationId, $agent, $provider->name(), $model, $e));
    }

    /**
     * A random version 4 UUID.
     */
    private static function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        // Set the version (4) and variant (8, 9, a or b) nibbles.
        $hex[12] = '4';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }
}
