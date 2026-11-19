<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Gateway\Anthropic;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Exceptions\InsufficientCreditsException;
use UpturnStudio\Ai\Exceptions\InvalidApiKeyException;
use UpturnStudio\Ai\Exceptions\ProviderConnectionException;
use UpturnStudio\Ai\Exceptions\ProviderOverloadedException;
use UpturnStudio\Ai\Exceptions\RateLimitedException;
use UpturnStudio\Ai\Http\RequestException;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Test\Unit\AiTestCase;
use UpturnStudio\Ai\Test\Unit\Fixtures\RandomNumberGenerator;
use UpturnStudio\Ai\Test\Unit\Fixtures\SalesCoach;
use UpturnStudio\Ai\Test\Unit\Fixtures\ScoringAgent;
use UpturnStudio\Ai\Test\Unit\Fixtures\ToolAgent;

/**
 * The Anthropic gateway against mocked HTTP: request mapping, response parsing, errors and failover.
 *
 * @covers \UpturnStudio\Ai\Gateway\Anthropic\AnthropicGateway
 * @covers \UpturnStudio\Ai\Gateway\Anthropic\AnthropicSchemaSanitizer
 * @covers \UpturnStudio\Ai\Gateway\Concerns\HandlesFailoverErrors
 * @covers \UpturnStudio\Ai\Model\AiManager
 */
class AnthropicGatewayTest extends AiTestCase
{
    public function testSendsAMessagesRequestWithTheConfiguredKey(): void
    {
        $this->queueJson($this->anthropicText('Great call.') + ['x' => 1], 200, ['request-id' => 'req_1']);

        $response = (new SalesCoach())->prompt('Analyze this transcript');

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $request->getUri());
        $this->assertSame('sk-configured', $request->getHeaderLine('x-api-key'));
        $this->assertSame('2026-10-21', $request->getHeaderLine('anthropic-version'));

        $body = $this->sentBody();
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame(16000, $body['max_tokens']);
        $this->assertSame('You are a sales coach.', $body['system']);
        $this->assertSame([['role' => 'user', 'content' => 'Analyze this transcript']], $body['messages']);
        $this->assertArrayNotHasKey('tools', $body);
        $this->assertArrayNotHasKey('temperature', $body);

        $this->assertSame('Great call.', $response->text);
        $this->assertSame(10, $response->usage->inputTokens);
        $this->assertSame(5, $response->usage->outputTokens);
        $this->assertSame(15, $response->usage->totalTokens());
        $this->assertSame('anthropic', $response->meta->provider);
        $this->assertSame('claude-opus-5-5', $response->meta->model);
        $this->assertSame('req_1', $response->raw->header('Request-Id'));
        $this->assertSame('msg_1', $response->raw->json('id'));
    }

    public function testUsageIncludesCacheTokensInTheInputTotal(): void
    {
        $payload = $this->anthropicText('ok');
        $payload['usage'] += ['cache_read_input_tokens' => 100, 'cache_creation_input_tokens' => 20];
        $this->queueJson($payload);

        $usage = (new SalesCoach())->prompt('Hi')->usage;

        $this->assertSame(130, $usage->inputTokens);
        $this->assertSame(100, $usage->cacheReadInputTokens);
        $this->assertSame(20, $usage->cacheWriteInputTokens);
        $this->assertSame(10, $usage->uncachedInputTokens());
    }

    public function testToolRoundTripMapsToolUseAndToolResultBlocks(): void
    {
        $toolUse = $this->anthropicText('Let me pick.', 'tool_use');
        $toolUse['content'][] = [
            'type' => 'tool_use',
            'id' => 'toolu_1',
            'name' => 'RandomNumberGenerator',
            'input' => ['min' => 1, 'max' => 6],
        ];
        $this->queueJson($toolUse);
        $this->queueJson($this->anthropicText('It is 6.'));

        $response = (new ToolAgent([new RandomNumberGenerator()]))->prompt('Roll a die');

        $first = $this->sentBody(0);
        $this->assertSame('RandomNumberGenerator', $first['tools'][0]['name']);
        $this->assertSame(['min', 'max'], $first['tools'][0]['input_schema']['required']);
        $this->assertSame('integer', $first['tools'][0]['input_schema']['properties']['max']['type']);

        $second = $this->sentBody(1);
        $this->assertSame('assistant', $second['messages'][1]['role']);
        $this->assertSame('tool_use', $second['messages'][1]['content'][1]['type']);
        $this->assertSame([
            'role' => 'user',
            'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => '6']],
        ], $second['messages'][2]);

        $this->assertSame('It is 6.', $response->text);
        $this->assertSame(20, $response->usage->inputTokens, 'Usage is summed over both steps.');
        $this->assertSame(FinishReason::Stop, $response->finishReason);
    }

    public function testThinkingBlocksAreReplayedVerbatimOnTheNextStep(): void
    {
        $toolUse = $this->anthropicText('', 'tool_use');
        $toolUse['content'] = [
            ['type' => 'thinking', 'thinking' => 'Considering.', 'signature' => 'sig_abc'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'RandomNumberGenerator', 'input' => ['min' => 1, 'max' => 2]],
        ];
        $this->queueJson($toolUse);
        $this->queueJson($this->anthropicText('Done.'));

        $response = (new ToolAgent([new RandomNumberGenerator()]))->prompt('Go');

        $this->assertSame($toolUse['content'], $this->sentBody(1)['messages'][1]['content']);
        $this->assertSame('Considering.', $response->reasoning);
    }

    public function testStructuredOutputSendsASanitizedSchemaAndAttributesApply(): void
    {
        $this->queueJson($this->anthropicText('{"feedback":"Good","score":8}'));

        $response = (new ScoringAgent())->prompt('Score this');

        $body = $this->sentBody();
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertSame(2048, $body['max_tokens']);

        $schema = $body['output_config']['format']['schema'];
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertArrayNotHasKey('minimum', $schema['properties']['score']);
        $this->assertStringContainsString('minimum 1', $schema['properties']['score']['description']);

        $this->assertSame(8, $response['score']);
    }

    public function testOnDemandProvidersCarryTheirOwnKeyUrlAndModel(): void
    {
        $this->queueJson($this->anthropicText('ok'));

        $provider = Ai::build([
            'driver' => 'anthropic',
            'key' => 'sk-customer',
            'url' => 'https://gateway.example.com/anthropic/',
            'models' => ['text' => ['default' => 'claude-sonnet-5-5']],
            'headers' => ['X-Tenant-Id' => '42'],
        ]);

        (new SalesCoach())->prompt('Hi', provider: [$provider]);

        $request = $this->history[0]['request'];
        $this->assertStringStartsWith('ondemand_', $provider->name());
        $this->assertSame('https://gateway.example.com/anthropic/messages', (string) $request->getUri());
        $this->assertSame('sk-customer', $request->getHeaderLine('x-api-key'));
        $this->assertSame('42', $request->getHeaderLine('X-Tenant-Id'));
        $this->assertSame('claude-sonnet-5-5', $this->sentBody()['model']);
    }

    public function testFailsOverToTheNextProviderWhenTheFirstIsOverloaded(): void
    {
        $this->queueJson(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529);
        $this->queueJson($this->anthropicText('From the second provider.'));

        $response = (new SalesCoach())->prompt('Analyze this sales transcript...', provider: [
            Ai::build(['driver' => 'anthropic', 'key' => 'sk-first']),
            Ai::build([
                'driver' => 'anthropic',
                'key' => 'sk-second',
                'models' => ['text' => ['default' => 'claude-sonnet-5-5']],
            ]),
        ]);

        $this->assertSame('From the second provider.', $response->text);
        $this->assertSame('sk-first', $this->history[0]['request']->getHeaderLine('x-api-key'));
        $this->assertSame('sk-second', $this->history[1]['request']->getHeaderLine('x-api-key'));
        $this->assertSame('claude-sonnet-5-5', $this->sentBody(1)['model']);
        $this->assertContains('upturn_ai_agent_failed_over', $this->events);
        $this->assertNotContains('upturn_ai_agent_failed', $this->events);
    }

    public function testFailoverListKeyedByProviderChoosesModels(): void
    {
        $this->queueJson(['error' => ['type' => 'rate_limit_error', 'message' => 'Slow down']], 429);
        $this->queueJson($this->anthropicText('ok'));
        $second = Ai::build(['driver' => 'anthropic', 'key' => 'sk-second']);

        (new SalesCoach())->prompt('Hi', provider: ['anthropic' => 'claude-haiku-5-5', $second->name() => 'claude-sonnet-5-5']);

        $this->assertSame('claude-haiku-5-5', $this->sentBody(0)['model']);
        $this->assertSame('claude-sonnet-5-5', $this->sentBody(1)['model']);
    }

    public function testTheLastFailoverFailureIsThrown(): void
    {
        $this->queueJson(['error' => ['type' => 'rate_limit_error', 'message' => 'Slow down']], 429);

        try {
            (new SalesCoach())->prompt('Hi');
            $this->fail('Expected a RateLimitedException.');
        } catch (RateLimitedException $e) {
            $this->assertStringContainsString('Slow down', $e->getMessage());
            $this->assertContains('upturn_ai_agent_failed', $this->events);
        }
    }

    public function testAnInvalidKeyDoesNotFailOver(): void
    {
        $this->queueJson(['error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], 401);
        $this->queueJson($this->anthropicText('never reached'));

        try {
            (new SalesCoach())->prompt('Hi', provider: ['anthropic', Ai::build(['driver' => 'anthropic', 'key' => 'k'])]);
            $this->fail('Expected an InvalidApiKeyException.');
        } catch (InvalidApiKeyException) {
            $this->assertCount(1, $this->history);
        }
    }

    public function testBadRequestsAreRethrownUnchanged(): void
    {
        $this->queueJson(['error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens: too large']], 400);

        $this->expectException(RequestException::class);
        (new SalesCoach())->prompt('Hi');
    }

    public function testCreditAndConnectionFailuresAreFailoverable(): void
    {
        $this->queueJson(['error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low']], 400);
        try {
            (new SalesCoach())->prompt('Hi');
            $this->fail('Expected an InsufficientCreditsException.');
        } catch (InsufficientCreditsException) {
            $this->addToAssertionCount(1);
        }

        $this->queueJson(['error' => ['type' => 'api_error', 'message' => 'Bad gateway']], 502);
        try {
            (new SalesCoach())->prompt('Hi');
            $this->fail('Expected a ProviderOverloadedException.');
        } catch (ProviderOverloadedException) {
            $this->addToAssertionCount(1);
        }

        $this->mock->append(new ConnectException('Connection refused', new Request('POST', 'https://api.anthropic.com')));
        $this->expectException(ProviderConnectionException::class);
        (new SalesCoach())->prompt('Hi');
    }

    public function testBuildRejectsMissingDriversAndTakenNames(): void
    {
        try {
            Ai::build(['key' => 'k']);
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('driver', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        Ai::build(['driver' => 'anthropic', 'name' => 'anthropic']);
    }

    public function testUnknownDriversAreReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AI driver [nope] is not supported');

        (new SalesCoach())->prompt('Hi', provider: [Ai::build(['driver' => 'nope'])]);
    }
}
