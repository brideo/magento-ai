<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit;

use LogicException;
use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Exceptions\StrayPromptException;
use UpturnStudio\Ai\Exceptions\ToolExecutionException;
use UpturnStudio\Ai\Gateway\PendingStep;
use UpturnStudio\Ai\Gateway\StepResponse;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Messages\ToolResultMessage;
use UpturnStudio\Ai\Middleware\Stop;
use UpturnStudio\Ai\Prompts\AgentPrompt;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Responses\Data\ToolCall;
use UpturnStudio\Ai\Responses\StructuredAgentResponse;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Test\Unit\Fixtures\RandomNumberGenerator;
use UpturnStudio\Ai\Test\Unit\Fixtures\RepairingAgent;
use UpturnStudio\Ai\Test\Unit\Fixtures\SalesCoach;
use UpturnStudio\Ai\Test\Unit\Fixtures\ScoringAgent;
use UpturnStudio\Ai\Test\Unit\Fixtures\ToolAgent;
use UpturnStudio\Ai\Tools\ToolRequest;

use function UpturnStudio\Ai\agent;

/**
 * The agent loop, exercised entirely on fakes: no provider is called.
 *
 * @covers \UpturnStudio\Ai\Promptable
 * @covers \UpturnStudio\Ai\Model\Agent\AgentRunner
 * @covers \UpturnStudio\Ai\Gateway\TextGenerationLoop
 * @covers \UpturnStudio\Ai\Gateway\FakeTextGateway
 */
class AgentTest extends AiTestCase
{
    public function testFakedAgentReturnsResponsesInOrderAndRecordsPrompts(): void
    {
        SalesCoach::fake(['First response', 'Second response']);

        $first = (new SalesCoach())->prompt('Analyze this transcript');
        $second = (new SalesCoach())->prompt('And this one');

        $this->assertSame('First response', (string) $first);
        $this->assertSame('Second response', $second->text);
        $this->assertNotSame('', $first->invocationId);

        SalesCoach::assertPrompted('Analyze this transcript');
        SalesCoach::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('this one'));
        SalesCoach::assertNotPrompted('Something else');
        SalesCoach::assertPromptedTimes(2);
        $this->assertTrue(SalesCoach::isFaked());
        $this->assertCount(0, $this->history, 'A faked agent must not make HTTP requests.');
    }

    public function testFakeAnswersWithAPlaceholderUnlessStrayPromptsArePrevented(): void
    {
        SalesCoach::fake();
        $this->assertSame('Fake response for prompt: Hello', (new SalesCoach())->prompt('Hello')->text);

        SalesCoach::fake()->preventStrayPrompts();
        $this->expectException(StrayPromptException::class);
        (new SalesCoach())->prompt('Hello');
    }

    public function testFakeClosureReceivesThePrompt(): void
    {
        SalesCoach::fake(fn (string $prompt) => strtoupper($prompt));

        $this->assertSame('HELLO', (new SalesCoach())->prompt('hello')->text);
    }

    public function testNeverPromptedAssertion(): void
    {
        SalesCoach::fake();

        SalesCoach::assertNeverPrompted();
    }

    public function testStructuredAgentReturnsArrayAccessibleOutput(): void
    {
        ScoringAgent::fake([['feedback' => 'Ask more questions', 'score' => 7]]);

        $response = (new ScoringAgent())->prompt('Score this');

        $this->assertInstanceOf(StructuredAgentResponse::class, $response);
        $this->assertSame(7, $response['score']);
        $this->assertSame('Ask more questions', $response['feedback']);
        $this->assertTrue(isset($response['score']));
        $this->assertSame(['feedback' => 'Ask more questions', 'score' => 7], $response->toArray());
    }

    public function testStructuredFakeGeneratesDataFromTheSchemaWhenNoResponseIsGiven(): void
    {
        ScoringAgent::fake();

        $response = (new ScoringAgent())->prompt('Score this');

        $this->assertIsString($response['feedback']);
        $this->assertSame(1, $response['score']);
    }

    public function testAttributesChooseProviderAndModel(): void
    {
        ScoringAgent::fake();
        (new ScoringAgent())->prompt('Score this');

        ScoringAgent::assertPrompted(
            fn (AgentPrompt $prompt) => $prompt->model === 'claude-haiku-5-5' && $prompt->provider->name() === 'anthropic'
        );
    }

    public function testPromptArgumentsOverrideAttributes(): void
    {
        ScoringAgent::fake();
        (new ScoringAgent())->prompt('Score this', model: 'claude-opus-5-5', timeout: 120);

        ScoringAgent::assertPrompted(
            fn (AgentPrompt $prompt) => $prompt->model === 'claude-opus-5-5' && $prompt->timeout === 120
        );
    }

    public function testToolLoopRunsToolsAndFeedsResultsBack(): void
    {
        $tool = new RandomNumberGenerator();
        $seen = [];

        ToolAgent::fake(function (string $prompt, array $messages) use (&$seen) {
            $seen[] = $messages;

            return count($seen) === 1
                ? new StepResponse(
                    toolCalls: [new ToolCall('call_1', 'RandomNumberGenerator', ['min' => 1, 'max' => 9])],
                    finishReason: FinishReason::ToolCalls
                )
                : 'The number is 9.';
        });

        $response = (new ToolAgent([$tool]))->prompt('Pick a number');

        $this->assertSame('The number is 9.', $response->text);
        $this->assertSame([['min' => 1, 'max' => 9]], $tool->calls);
        $this->assertCount(2, $response->steps);
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('9', $response->toolResults[0]->result);
        $this->assertInstanceOf(ToolResultMessage::class, end($seen[1]));
        $this->assertContains('upturn_ai_invoking_tool', $this->events);
        $this->assertContains('upturn_ai_tool_invoked', $this->events);
    }

    public function testInvalidToolArgumentsGoBackToTheModelAsAnError(): void
    {
        $tool = new RandomNumberGenerator();
        $step = 0;

        ToolAgent::fake(function () use (&$step) {
            return ++$step === 1
                ? new StepResponse(toolCalls: [new ToolCall('call_1', 'RandomNumberGenerator', ['min' => 'low'])])
                : 'Done';
        });

        $response = (new ToolAgent([$tool]))->prompt('Pick a number');

        $this->assertSame([], $tool->calls);
        $this->assertTrue($response->toolResults[0]->isError);
        $this->assertStringContainsString('[max] is required', $response->toolResults[0]->result);
    }

    public function testUnknownToolFailsTheRunUnlessRepairIsEnabled(): void
    {
        $responses = fn () => function () {
            static $step = 0;

            return ++$step === 1 ? new StepResponse(toolCalls: [new ToolCall('call_1', 'Missing', [])]) : 'Recovered';
        };

        RepairingAgent::fake($responses());
        $response = (new RepairingAgent([new RandomNumberGenerator()]))->prompt('Go');

        $this->assertSame('Recovered', $response->text);
        $this->assertStringContainsString('Available tools: RandomNumberGenerator', $response->toolResults[0]->result);

        ToolAgent::fake($responses());
        $this->expectException(AiException::class);
        $this->expectExceptionMessage('Tool [Missing] does not exist');
        (new ToolAgent([new RandomNumberGenerator()]))->prompt('Go');
    }

    public function testAThrowingToolFailsTheRun(): void
    {
        $tool = new class extends RandomNumberGenerator {
            public function name(): string
            {
                return 'explode';
            }

            public function handle(ToolRequest $request): string
            {
                throw new \RuntimeException('boom');
            }
        };

        ToolAgent::fake([new StepResponse(toolCalls: [new ToolCall('call_1', 'explode', ['min' => 1, 'max' => 2])])]);

        try {
            (new ToolAgent([$tool]))->prompt('Go');
            $this->fail('Expected a ToolExecutionException.');
        } catch (ToolExecutionException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
            $this->assertContains('upturn_ai_tool_failed', $this->events);
            $this->assertContains('upturn_ai_agent_failed', $this->events);
        }
    }

    public function testWithToolsReplacesOrExtendsTheDeclaredTools(): void
    {
        ToolAgent::fake();
        $declared = new RandomNumberGenerator();

        (new ToolAgent([$declared]))->withTools([])->prompt('one');
        (new ToolAgent([$declared]))->withTools(fn (array $tools) => [...$tools, new RandomNumberGenerator()])->prompt('two');

        ToolAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'one' && $prompt->tools === []);
        ToolAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'two' && count($prompt->tools) === 2);
    }

    public function testMiddlewareCanChangeAStepAndObserveTheResponse(): void
    {
        $models = [];
        $texts = [];

        ToolAgent::fake(function (string $prompt, array $messages, $provider, string $model) use (&$models) {
            $models[] = $model;

            return 'ok';
        });

        $middleware = function (PendingStep $step, \Closure $next) use (&$texts) {
            return $next($step->withModel('claude-haiku-5-5'))
                ->then(function (StepResponse $response) use (&$texts) {
                    $texts[] = $response->text;
                });
        };

        (new ToolAgent([], [$middleware]))->prompt('Go');

        $this->assertSame(['claude-haiku-5-5'], $models);
        $this->assertSame(['ok'], $texts);
    }

    public function testStopMiddlewareEndsTheRunBeforeTheModelIsCalled(): void
    {
        ToolAgent::fake()->preventStrayPrompts();

        $response = (new ToolAgent([], [Stop::when(fn (PendingStep $step) => $step->isFirstStep())]))->prompt('Go');

        $this->assertSame('', $response->text);
        $this->assertSame(FinishReason::Cancelled, $response->finishReason);
        $this->assertSame([], $response->steps);
    }

    public function testMiddlewareMustReturnAStepResult(): void
    {
        ToolAgent::fake();

        $this->expectException(LogicException::class);
        (new ToolAgent([], [fn () => 'nope']))->prompt('Go');
    }

    public function testWithMessagesPassesHistoryForOneRun(): void
    {
        $seen = [];
        SalesCoach::fake(function (string $prompt, array $messages) use (&$seen) {
            $seen = $messages;

            return 'ok';
        });

        $agent = new SalesCoach();
        $agent->withMessages([
            new Message('user', 'Hi'),
            ['role' => 'assistant', 'content' => 'Hello'],
        ])->prompt('What next?');

        $this->assertSame(['Hi', 'Hello', 'What next?'], array_map(fn (Message $m) => $m->content, $seen));

        $agent->prompt('Fresh');
        $this->assertCount(1, $seen, 'withMessages() must not change the original agent.');
    }

    public function testWithMessagesIsRejectedOnConversationalAgents(): void
    {
        $this->expectException(LogicException::class);
        // The anonymous agent overrides withMessages(), so use a class that only has the trait's version.
        (new class extends SalesCoach implements \UpturnStudio\Ai\Api\ConversationalInterface {
            public function messages(): iterable
            {
                return [];
            }
        })->withMessages([]);
    }

    public function testAnonymousAgentsPromptAndReturnStructuredOutput(): void
    {
        $this->queueJson($this->anthropicText('Magento is an e-commerce platform.'));
        $this->queueJson($this->anthropicText('{"number": 42}'));

        $text = agent(instructions: 'Be brief.')->prompt('What is Magento?');
        $structured = agent(schema: fn (JsonSchema $schema) => ['number' => $schema->integer()->required()])
            ->prompt('Pick a number');

        $this->assertSame('Magento is an e-commerce platform.', $text->text);
        $this->assertSame('Be brief.', $this->sentBody(0)['system']);
        $this->assertSame(42, $structured['number']);
        $this->assertSame('json_schema', $this->sentBody(1)['output_config']['format']['type']);
    }

    public function testEventsAreDispatchedAroundARun(): void
    {
        SalesCoach::fake();
        (new SalesCoach())->prompt('Hello');

        $this->assertSame([
            'upturn_ai_prompting_agent',
            'upturn_ai_starting_step',
            'upturn_ai_step_completed',
            'upturn_ai_agent_prompted',
        ], $this->events);
    }

    public function testFakedAgentsWorkWithNoProviderConfigured(): void
    {
        Ai::reset();
        SalesCoach::fake(['ok']);

        $this->assertSame('ok', (new SalesCoach())->prompt('Hello', provider: 'not-configured')->text);
    }
}
