<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway;

use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\HasProviderOptionsInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Attributes\CacheInstructions;
use UpturnStudio\Ai\Attributes\CacheToolDefinitions;
use UpturnStudio\Ai\Attributes\MaxSteps;
use UpturnStudio\Ai\Attributes\MaxTokens;
use UpturnStudio\Ai\Attributes\RepairToolCalls;
use UpturnStudio\Ai\Attributes\Strict;
use UpturnStudio\Ai\Attributes\Temperature;
use UpturnStudio\Ai\Attributes\TopP;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Model\Agent\AttributeReader;

/**
 * The generation settings of a run, read from the agent's attributes and methods.
 */
class TextGenerationOptions
{
    /**
     * @param int|null $maxSteps
     * @param int|null $maxTokens
     * @param float|null $temperature
     * @param float|null $topP
     * @param array<string,mixed> $providerOptions Extra request-body fields for the provider.
     * @param string|null $cacheInstructions Cache lifetime for the instructions breakpoint, or null for none.
     * @param string|null $cacheToolDefinitions Cache lifetime for the tools breakpoint, or null for none.
     * @param bool $strict
     * @param bool $repairToolCalls
     */
    public function __construct(
        public readonly ?int $maxSteps = null,
        public readonly ?int $maxTokens = null,
        public readonly ?float $temperature = null,
        public readonly ?float $topP = null,
        public readonly array $providerOptions = [],
        public readonly ?string $cacheInstructions = null,
        public readonly ?string $cacheToolDefinitions = null,
        public readonly bool $strict = false,
        public readonly bool $repairToolCalls = false
    ) {
    }

    /**
     * Read an agent's settings for one provider.
     *
     * @param AgentInterface $agent
     * @param ProviderInterface $provider
     */
    public static function forAgent(AgentInterface $agent, ProviderInterface $provider): self
    {
        $providerOptions = [];
        if ($agent instanceof HasProviderOptionsInterface) {
            $driver = $provider->driver();
            $providerOptions = $agent->providerOptions(Lab::tryFrom($driver) ?? $driver);
        }

        $temperature = AttributeReader::value($agent, Temperature::class, 'temperature');
        $topP = AttributeReader::value($agent, TopP::class, 'topP');

        return new self(
            AttributeReader::value($agent, MaxSteps::class, 'maxSteps'),
            AttributeReader::value($agent, MaxTokens::class, 'maxTokens'),
            $temperature === null ? null : (float) $temperature,
            $topP === null ? null : (float) $topP,
            $providerOptions,
            AttributeReader::value($agent, CacheInstructions::class),
            AttributeReader::value($agent, CacheToolDefinitions::class),
            AttributeReader::has($agent, Strict::class),
            AttributeReader::has($agent, RepairToolCalls::class)
        );
    }

    /**
     * A copy with some settings replaced.
     *
     * @param array<string,mixed> $overrides Keyed by constructor parameter name.
     */
    public function with(array $overrides): self
    {
        return new self(...array_merge(get_object_vars($this), $overrides));
    }
}
