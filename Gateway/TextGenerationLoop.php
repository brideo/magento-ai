<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway;

use Closure;
use Throwable;
use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\HasMiddlewareInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Api\ToolInterface;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Events\InvokingTool;
use UpturnStudio\Ai\Events\StartingStep;
use UpturnStudio\Ai\Events\StepCompleted;
use UpturnStudio\Ai\Events\StepFailed;
use UpturnStudio\Ai\Events\ToolFailed;
use UpturnStudio\Ai\Events\ToolInvoked;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Exceptions\ToolExecutionException;
use UpturnStudio\Ai\Exceptions\ToolValidationException;
use UpturnStudio\Ai\Messages\AssistantMessage;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Messages\ToolResultMessage;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Responses\Data\Meta;
use UpturnStudio\Ai\Responses\Data\Step;
use UpturnStudio\Ai\Responses\Data\TextUsage;
use UpturnStudio\Ai\Responses\Data\ToolCall;
use UpturnStudio\Ai\Responses\Data\ToolResult;
use UpturnStudio\Ai\Responses\TextResponse;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Tools\ToolNameResolver;
use UpturnStudio\Ai\Tools\ToolRequest;

/**
 * The multi-step loop behind every agent run: call the model, run the tools it asks for, send the results
 * back, and repeat until it answers or the step limit is reached. Gateways only ever do one round-trip.
 */
class TextGenerationLoop
{
    private const MAX_AUTOMATIC_STEPS = 25;

    /**
     * @param TextGatewayInterface $gateway
     * @param Dispatcher $events
     */
    public function __construct(
        private readonly TextGatewayInterface $gateway,
        private readonly Dispatcher $events
    ) {
    }

    /**
     * Run to completion and return the response.
     *
     * @param TextProviderInterface $provider
     * @param string $model
     * @param string|null $instructions
     * @param Message[] $messages
     * @param array<int,object> $tools
     * @param array<string,mixed>|null $schema
     * @param TextGenerationOptions $options
     * @param int|null $timeout
     * @param AgentInterface|null $agent Supplies the step middleware.
     * @param string $invocationId
     */
    public function generate(
        TextProviderInterface $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options,
        ?int $timeout = null,
        ?AgentInterface $agent = null,
        string $invocationId = ''
    ): TextResponse {
        $maxSteps = $this->resolveMaxSteps($options, $tools);
        $middleware = $agent instanceof HasMiddlewareInterface ? $agent->middleware() : [];

        $steps = [];
        $produced = [];
        $usage = new TextUsage();
        $last = null;
        $finishReason = FinishReason::Stop;

        for ($number = 1; $number <= $maxSteps; $number++) {
            $pending = new PendingStep(
                $number,
                $number === $maxSteps,
                $provider,
                $model,
                $instructions,
                $messages,
                $tools,
                $schema,
                $options,
                $steps,
                $usage,
                $timeout,
                $invocationId
            );

            $this->events->dispatch(new StartingStep($invocationId, $pending));

            try {
                $sent = $pending;
                $result = $this->runStep($pending, $middleware, $sent);

                if ($result->isStopped()) {
                    $finishReason = FinishReason::Cancelled;
                    break;
                }

                $last = $result->response();
                $finishReason = $last->finishReason;
                $usage = $usage->add($last->usage);

                $toolResults = [];
                if ($last->toolCalls !== [] && !$pending->isFinalStep) {
                    foreach ($last->toolCalls as $toolCall) {
                        $toolResults[] = $this->executeTool($toolCall, $sent->tools, $options, $invocationId);
                    }
                }
            } catch (Throwable $e) {
                $this->events->dispatch(new StepFailed($invocationId, $number, $e));
                throw $e;
            }

            $step = new Step(
                $last->text,
                $last->toolCalls,
                $toolResults,
                $last->finishReason,
                $last->usage,
                $last->meta,
                $last->reasoning,
                $last->raw
            );
            $steps[] = $step;
            $this->events->dispatch(new StepCompleted($invocationId, $number, $step));

            $assistant = new AssistantMessage($last->text, $last->toolCalls, $last->replayBlocks);
            $messages[] = $assistant;
            $produced[] = $assistant;

            if ($toolResults !== []) {
                $toolMessage = new ToolResultMessage($toolResults);
                $messages[] = $toolMessage;
                $produced[] = $toolMessage;
                continue;
            }

            if ($last->finishReason !== FinishReason::Continue) {
                break;
            }
        }

        return new TextResponse(
            $last?->text ?? '',
            $usage,
            $last?->meta ?? new Meta($provider->name(), $model),
            $produced,
            array_merge(...array_map(static fn (Step $step) => $step->toolCalls, $steps)),
            array_merge(...array_map(static fn (Step $step) => $step->toolResults, $steps)),
            $steps,
            implode("\n\n", array_filter(array_map(static fn (Step $step) => $step->reasoning, $steps))),
            $this->structuredOutput($last, $schema),
            $finishReason,
            $last?->raw
        );
    }

    /**
     * The step limit: an explicit MaxSteps, otherwise scaled to the number of tools.
     *
     * @param TextGenerationOptions $options
     * @param array<int,object> $tools
     */
    private function resolveMaxSteps(TextGenerationOptions $options, array $tools): int
    {
        if ($options->maxSteps !== null) {
            return max(1, $options->maxSteps);
        }

        $steps = $tools === [] ? 5 : (int) min(max(5, round(count($tools) * 1.5)), self::MAX_AUTOMATIC_STEPS);

        return $options->repairToolCalls ? $steps + 1 : $steps;
    }

    /**
     * Pass a step through the agent's middleware to the gateway.
     *
     * @param PendingStep $step
     * @param array<int,object|callable> $middleware
     * @param PendingStep $sent Receives the step as it reached the gateway, after middleware changed it.
     */
    private function runStep(PendingStep $step, array $middleware, PendingStep &$sent): StepResult
    {
        $core = function (PendingStep $step) use (&$sent): StepResult {
            $sent = $step;

            return new StepResult($this->gateway->generateTextStep(
                $step->provider,
                $step->model,
                $step->instructions,
                $step->messages,
                $step->tools,
                $step->schema,
                $step->options,
                $step->timeout
            ));
        };

        $pipeline = array_reduce(
            array_reverse($middleware),
            static fn (Closure $next, object|callable $layer): Closure => static fn (PendingStep $step): StepResult
                => StepResult::wrap(
                    is_object($layer) && method_exists($layer, 'handle')
                        ? $layer->handle($step, $next)
                        : $layer($step, $next)
                ),
            $core
        );

        return $pipeline($step);
    }

    /**
     * Run one tool call and return its result.
     *
     * @param ToolCall $toolCall
     * @param array<int,object> $tools
     * @param TextGenerationOptions $options
     * @param string $invocationId
     * @throws AiException When the tool does not exist and repair is off.
     * @throws ToolExecutionException When the tool throws.
     */
    private function executeTool(
        ToolCall $toolCall,
        array $tools,
        TextGenerationOptions $options,
        string $invocationId
    ): ToolResult {
        $available = [];
        foreach ($tools as $candidate) {
            if ($candidate instanceof ToolInterface) {
                $available[ToolNameResolver::resolve($candidate)] = $candidate;
            }
        }

        $tool = $available[$toolCall->name] ?? null;

        if ($tool === null) {
            $message = sprintf(
                'Tool [%s] does not exist. Available tools: %s.',
                $toolCall->name,
                implode(', ', array_keys($available)) ?: 'none'
            );

            if (!$options->repairToolCalls) {
                throw new AiException($message);
            }

            return new ToolResult($toolCall->id, $toolCall->name, $toolCall->arguments, $message, true);
        }

        $this->events->dispatch(new InvokingTool($invocationId, $tool, $toolCall));

        try {
            $request = new ToolRequest(
                $toolCall->arguments,
                $toolCall->id,
                JsonSchema::fromProperties($tool->schema(new JsonSchema()))
            );
            $result = new ToolResult(
                $toolCall->id,
                $toolCall->name,
                $toolCall->arguments,
                (string) $tool->handle($request)
            );
        } catch (ToolValidationException $e) {
            $result = new ToolResult($toolCall->id, $toolCall->name, $toolCall->arguments, $e->getMessage(), true);
        } catch (Throwable $e) {
            $this->events->dispatch(new ToolFailed($invocationId, $tool, $toolCall, $e));

            throw new ToolExecutionException(
                sprintf('Tool [%s] failed: %s', $toolCall->name, $e->getMessage()),
                0,
                $e
            );
        }

        $this->events->dispatch(new ToolInvoked($invocationId, $tool, $toolCall, $result));

        return $result;
    }

    /**
     * The decoded structured output of the final step, when a schema was requested.
     *
     * @param StepResponse|null $response
     * @param array<string,mixed>|null $schema
     * @return array<string, mixed>|null
     */
    private function structuredOutput(?StepResponse $response, ?array $schema): ?array
    {
        if ($schema === null || $response === null) {
            return null;
        }

        if ($response->structured !== null) {
            return $response->structured;
        }

        $decoded = json_decode($response->text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
