<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai;

use Closure;
use LogicException;
use PHPUnit\Framework\Assert;
use UpturnStudio\Ai\Api\ConversationalInterface;
use UpturnStudio\Ai\Api\HasToolsInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Gateway\FakeTextGateway;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Prompts\AgentPrompt;
use UpturnStudio\Ai\Responses\AgentResponse;

/**
 * Gives an agent prompt(), per-run overrides and the testing helpers. Every agent uses it.
 */
trait Promptable
{
    /** @var Message[] */
    private array $adHocMessages = [];

    /**
     * Tools that replace the declared ones for one run.
     *
     * @var Closure|array|null
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing
    private Closure|array|null $runtimeTools = null;

    /**
     * Create the agent through the ObjectManager, so its constructor dependencies are injected.
     *
     * @param mixed $arguments
     */
    public static function make(mixed ...$arguments): static
    {
        /** @var static $agent */
        $agent = Ai::make(static::class, $arguments);

        return $agent;
    }

    /**
     * Send a prompt and wait for the response.
     *
     * @param string $prompt
     * @param array<int,object> $attachments
     * @param Lab|ProviderInterface|array|string|null $provider A provider, or an array of them to fail over in order.
     * @param string|null $model
     * @param int|null $timeout Seconds per provider request.
     */
    public function prompt(
        string $prompt,
        array $attachments = [],
        Lab|ProviderInterface|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null
    ): AgentResponse {
        return Ai::runner()->prompt(
            $this,
            $prompt,
            $attachments,
            $provider,
            $model,
            $timeout,
            $this->adHocMessages,
            $this->resolveRuntimeTools()
        );
    }

    /**
     * Pass conversation history for one run. Conversational agents supply their own and cannot use this.
     *
     * @param iterable<Message|array{role:string,content:string}> $messages
     */
    public function withMessages(iterable $messages): static
    {
        if ($this instanceof ConversationalInterface) {
            throw new LogicException(
                'withMessages() cannot be used on a conversational agent; it supplies its own messages().'
            );
        }

        $agent = clone $this;
        $agent->adHocMessages = [];
        foreach ($messages as $message) {
            $agent->adHocMessages[] = Message::from($message);
        }

        return $agent;
    }

    /**
     * Replace the agent's tools for one run. A closure receives the declared tools and returns the new list.
     *
     * @param Closure|iterable<object> $tools
     */
    public function withTools(Closure|iterable $tools): static
    {
        $agent = clone $this;
        $agent->runtimeTools = $tools instanceof Closure ? $tools : [...$tools];

        return $agent;
    }

    /**
     * Fake this agent: prompts return the given responses, in order, instead of calling a provider.
     *
     * @param Closure|array $responses Strings, arrays for structured output, or a closure receiving the prompt.
     */
    public static function fake(Closure|array $responses = []): FakeTextGateway
    {
        return Ai::manager()->fakeAgent(static::class, $responses);
    }

    /**
     * Whether this agent is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::manager()->hasFakeAgent(static::class);
    }

    /**
     * Assert the agent received a prompt equal to a string, or matching a closure over the AgentPrompt.
     *
     * @param Closure|string $callback
     */
    public static function assertPrompted(Closure|string $callback): void
    {
        Assert::assertTrue(
            self::promptsMatching($callback) !== [],
            'An expected prompt was not received.'
        );
    }

    /**
     * Assert the agent did not receive a matching prompt.
     *
     * @param Closure|string $callback
     */
    public static function assertNotPrompted(Closure|string $callback): void
    {
        Assert::assertSame([], self::promptsMatching($callback), 'An unexpected prompt was received.');
    }

    /**
     * Assert how many prompts the agent received.
     *
     * @param int $times
     */
    public static function assertPromptedTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            Ai::manager()->recordedPrompts(static::class),
            sprintf('The agent was not prompted %d time(s).', $times)
        );
    }

    /**
     * Assert the agent received no prompts at all.
     */
    public static function assertNeverPrompted(): void
    {
        self::assertPromptedTimes(0);
    }

    /**
     * The recorded prompts matching a string or closure.
     *
     * @param Closure|string $callback
     * @return AgentPrompt[]
     */
    private static function promptsMatching(Closure|string $callback): array
    {
        $matches = is_string($callback)
            ? static fn (AgentPrompt $prompt): bool => $prompt->prompt === $callback
            : $callback;

        return array_values(array_filter(Ai::manager()->recordedPrompts(static::class), $matches));
    }

    /**
     * The tools for this run when they were overridden, otherwise null.
     *
     * @return array<int, object>|null
     */
    private function resolveRuntimeTools(): ?array
    {
        if ($this->runtimeTools === null) {
            return null;
        }

        if ($this->runtimeTools instanceof Closure) {
            $declared = $this instanceof HasToolsInterface ? [...$this->tools()] : [];

            return array_values([...($this->runtimeTools)($declared)]);
        }

        return array_values($this->runtimeTools);
    }
}
