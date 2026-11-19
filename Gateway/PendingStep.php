<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway;

use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Responses\Data\Step;
use UpturnStudio\Ai\Responses\Data\TextUsage;
use UpturnStudio\Ai\Tools\ToolNameResolver;

/**
 * One generation step, before the model is called. Middleware receives it and may pass on an altered copy.
 */
class PendingStep
{
    /**
     * @param int $number 1 for the first step.
     * @param bool $isFinalStep Whether this is the last step the run is allowed.
     * @param TextProviderInterface $provider
     * @param string $model
     * @param string|null $instructions
     * @param Message[] $messages
     * @param array<int,object> $tools
     * @param array<string,mixed>|null $schema
     * @param TextGenerationOptions $options
     * @param Step[] $steps The steps completed so far.
     * @param TextUsage $usage Usage so far.
     * @param int|null $timeout
     * @param string $invocationId
     */
    public function __construct(
        public readonly int $number,
        public readonly bool $isFinalStep,
        public readonly TextProviderInterface $provider,
        public readonly string $model,
        public readonly ?string $instructions,
        public readonly array $messages,
        public readonly array $tools,
        public readonly ?array $schema,
        public readonly TextGenerationOptions $options,
        public readonly array $steps,
        public readonly TextUsage $usage,
        public readonly ?int $timeout = null,
        public readonly string $invocationId = ''
    ) {
    }

    /**
     * Whether this is the first step of the run.
     */
    public function isFirstStep(): bool
    {
        return $this->number === 1;
    }

    /**
     * Use another model for this step.
     *
     * @param string $model
     */
    public function withModel(string $model): self
    {
        return $this->with(['model' => $model]);
    }

    /**
     * Use other instructions for this step.
     *
     * @param string|null $instructions
     */
    public function withInstructions(?string $instructions): self
    {
        return $this->with(['instructions' => $instructions]);
    }

    /**
     * Send other messages for this step only. The run's history is not changed.
     *
     * @param Message[] $messages
     */
    public function withMessages(array $messages): self
    {
        return $this->with(['messages' => $messages]);
    }

    /**
     * Offer other tools for this step.
     *
     * @param array<int,object> $tools
     */
    public function withTools(array $tools): self
    {
        return $this->with(['tools' => array_values($tools)]);
    }

    /**
     * Offer only the named tools for this step.
     *
     * @param string $names
     */
    public function onlyTools(string ...$names): self
    {
        return $this->withTools(array_filter(
            $this->tools,
            static fn (object $tool) => in_array(ToolNameResolver::resolve($tool), $names, true)
        ));
    }

    /**
     * Withhold the named tools for this step, or every tool when no name is given.
     *
     * @param string $names
     */
    public function withoutTools(string ...$names): self
    {
        if ($names === []) {
            return $this->withTools([]);
        }

        return $this->withTools(array_filter(
            $this->tools,
            static fn (object $tool) => !in_array(ToolNameResolver::resolve($tool), $names, true)
        ));
    }

    /**
     * Cap the tokens generated in this step.
     *
     * @param int $maxTokens
     */
    public function withMaxTokens(int $maxTokens): self
    {
        return $this->with(['options' => $this->options->with(['maxTokens' => $maxTokens])]);
    }

    /**
     * Merge extra request-body fields into this step.
     *
     * @param array<string,mixed> $providerOptions
     */
    public function withProviderOptions(array $providerOptions): self
    {
        return $this->with(['options' => $this->options->with([
            'providerOptions' => array_replace_recursive($this->options->providerOptions, $providerOptions),
        ])]);
    }

    /**
     * A copy with some properties replaced.
     *
     * @param array<string,mixed> $overrides
     */
    private function with(array $overrides): self
    {
        return new self(...array_merge(get_object_vars($this), $overrides));
    }
}
