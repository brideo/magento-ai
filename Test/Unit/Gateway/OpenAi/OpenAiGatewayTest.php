<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Gateway\OpenAi;

use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Exceptions\InsufficientCreditsException;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Test\Unit\AiTestCase;
use UpturnStudio\Ai\Test\Unit\Fixtures\RandomNumberGenerator;
use UpturnStudio\Ai\Test\Unit\Fixtures\SalesCoach;
use UpturnStudio\Ai\Test\Unit\Fixtures\ScoringAgent;
use UpturnStudio\Ai\Test\Unit\Fixtures\ToolAgent;

/**
 * @covers \UpturnStudio\Ai\Gateway\OpenAi\OpenAiGateway
 * @covers \UpturnStudio\Ai\Model\Provider\OpenAiProvider
 */
class OpenAiGatewayTest extends AiTestCase
{
    /**
     * @param array<int, array<string, mixed>> $output
     * @return array<string, mixed>
     */
    private function response(array $output, string $model = 'gpt-6.1-sol'): array
    {
        return [
            'id' => 'resp_1',
            'status' => 'completed',
            'model' => $model,
            'output' => $output,
            'usage' => [
                'input_tokens' => 20,
                'output_tokens' => 8,
                'input_tokens_details' => ['cached_tokens' => 5],
                'output_tokens_details' => ['reasoning_tokens' => 3],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $text): array
    {
        return [
            'type' => 'message',
            'id' => 'msg_1',
            'status' => 'completed',
            'role' => 'assistant',
            'content' => [['type' => 'output_text', 'text' => $text]],
        ];
    }

    public function testSendsAResponsesRequest(): void
    {
        $this->queueJson($this->response([$this->message('Great call.')]));

        $response = (new SalesCoach())->prompt('Analyze this', provider: [
            Ai::build(['driver' => 'openai', 'key' => 'sk-openai']),
        ]);

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.openai.com/v1/responses', (string) $request->getUri());
        $this->assertSame('Bearer sk-openai', $request->getHeaderLine('Authorization'));

        $body = $this->sentBody();
        $this->assertSame('gpt-6.1-sol', $body['model']);
        $this->assertSame([
            ['role' => 'system', 'content' => 'You are a sales coach.'],
            ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Analyze this']]],
        ], $body['input']);
        $this->assertSame(['reasoning.encrypted_content'], $body['include']);
        $this->assertArrayNotHasKey('store', $body);
        $this->assertArrayNotHasKey('tools', $body);

        $this->assertSame('Great call.', $response->text);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertSame(20, $response->usage->inputTokens);
        $this->assertSame(5, $response->usage->cacheReadInputTokens);
        $this->assertSame(3, $response->usage->reasoningTokens);
        $this->assertSame('gpt-6.1-sol', $response->meta->model);
    }

    public function testStatelessProvidersSendStoreFalse(): void
    {
        $this->queueJson($this->response([$this->message('ok')]));

        (new SalesCoach())->prompt('Hi', provider: [
            Ai::build(['driver' => 'openai', 'key' => 'k', 'store' => false, 'url' => 'https://proxy.test/v1/']),
        ], model: 'gpt-4.1');

        $this->assertSame('https://proxy.test/v1/responses', (string) $this->history[0]['request']->getUri());
        $this->assertFalse($this->sentBody()['store']);
        $this->assertArrayNotHasKey('include', $this->sentBody(), 'Only reasoning models ask for encrypted reasoning.');
    }

    public function testToolRoundTripReplaysOutputItemsAndAnswersByCallId(): void
    {
        $reasoning = ['type' => 'reasoning', 'id' => 'rs_1', 'summary' => [['type' => 'summary_text', 'text' => 'Roll it.']]];
        $call = [
            'type' => 'function_call',
            'id' => 'fc_1',
            'call_id' => 'call_1',
            'status' => 'completed',
            'name' => 'RandomNumberGenerator',
            'arguments' => '{"min":1,"max":6}',
        ];
        $this->queueJson($this->response([$reasoning, $call]));
        $this->queueJson($this->response([$this->message('It is 6.')]));

        $response = (new ToolAgent([new RandomNumberGenerator()]))->prompt('Roll a die', provider: [
            Ai::build(['driver' => 'openai', 'key' => 'k']),
        ]);

        $first = $this->sentBody(0);
        $this->assertSame('auto', $first['tool_choice']);
        $this->assertSame('function', $first['tools'][0]['type']);
        $this->assertSame('RandomNumberGenerator', $first['tools'][0]['name']);
        $this->assertFalse($first['tools'][0]['strict']);
        $this->assertSame(['min', 'max'], $first['tools'][0]['parameters']['required']);

        $input = $this->sentBody(1)['input'];
        $this->assertSame($reasoning, $input[2]);
        $this->assertSame($call, $input[3]);
        $this->assertSame(['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => '6'], $input[4]);

        $this->assertSame('It is 6.', $response->text);
        $this->assertSame('Roll it.', $response->reasoning);
        $this->assertSame('call_1', $response->toolCalls[0]->id);
    }

    public function testStructuredOutputUsesTextFormat(): void
    {
        $this->queueJson($this->response([$this->message('{"feedback":"Good","score":8}')]));

        $response = (new ScoringAgent())->prompt('Score this', provider: [
            Ai::build(['driver' => 'openai', 'key' => 'k']),
        ]);

        $format = $this->sentBody()['text']['format'];
        $this->assertSame('json_schema', $format['type']);
        $this->assertSame('schema_definition', $format['name']);
        $this->assertSame(['feedback', 'score'], $format['schema']['required']);
        $this->assertSame(2048, $this->sentBody()['max_output_tokens']);
        $this->assertSame(8, $response['score']);
    }

    public function testIncompleteAndFailedResponses(): void
    {
        $incomplete = $this->response([$this->message('Partial')]);
        $incomplete['status'] = 'incomplete';
        $incomplete['incomplete_details'] = ['reason' => 'max_output_tokens'];
        $this->queueJson($incomplete);

        $provider = Ai::build(['driver' => 'openai', 'key' => 'k']);
        $this->assertSame(FinishReason::Length, (new SalesCoach())->prompt('Hi', provider: [$provider])->finishReason);

        $this->queueJson(['status' => 'failed', 'error' => ['code' => 'server_error', 'message' => 'It broke']]);
        $this->expectException(AiException::class);
        $this->expectExceptionMessage('It broke');
        (new SalesCoach())->prompt('Hi', provider: [$provider]);
    }

    public function testQuotaErrorsAreFailoverable(): void
    {
        $this->queueJson(['error' => ['type' => 'insufficient_quota', 'code' => 'insufficient_quota', 'message' => 'Quota']], 429);
        $this->queueJson($this->anthropicText('From Anthropic.'));

        $response = (new SalesCoach())->prompt('Hi', provider: [
            Ai::build(['driver' => 'openai', 'key' => 'k']),
            'anthropic',
        ]);

        $this->assertSame('From Anthropic.', $response->text);
        $this->assertStringContainsString('api.anthropic.com', (string) $this->history[1]['request']->getUri());

        $this->queueJson(['error' => ['type' => 'insufficient_quota', 'message' => 'Quota']], 429);
        $this->expectException(InsufficientCreditsException::class);
        (new SalesCoach())->prompt('Hi', provider: [Ai::build(['driver' => 'openai', 'key' => 'k'])]);
    }
}
