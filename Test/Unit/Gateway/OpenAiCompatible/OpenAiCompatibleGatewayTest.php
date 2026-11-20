<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Gateway\OpenAiCompatible;

use InvalidArgumentException;
use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Attributes\Temperature;
use UpturnStudio\Ai\Test\Unit\AiTestCase;
use UpturnStudio\Ai\Test\Unit\Fixtures\RandomNumberGenerator;
use UpturnStudio\Ai\Test\Unit\Fixtures\SalesCoach;
use UpturnStudio\Ai\Test\Unit\Fixtures\ScoringAgent;
use UpturnStudio\Ai\Test\Unit\Fixtures\ToolAgent;

/**
 * @covers \UpturnStudio\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway
 * @covers \UpturnStudio\Ai\Model\Provider\OpenAiCompatibleProvider
 * @covers \UpturnStudio\Ai\Model\Provider\OllamaProvider
 */
class OpenAiCompatibleGatewayTest extends AiTestCase
{
    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function completion(array $message, string $finishReason = 'stop'): array
    {
        return [
            'id' => 'chatcmpl_1',
            'model' => 'local-model',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant'] + $message, 'finish_reason' => $finishReason]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 4],
        ];
    }

    public function testSendsAChatCompletionsRequest(): void
    {
        $this->queueJson($this->completion(['content' => 'Hello.']));

        $response = (new SalesCoach())->prompt('Hi', provider: [Ai::build([
            'driver' => 'openai-compatible',
            'url' => 'http://localhost:1234/v1/',
            'key' => 'local-key',
            'models' => ['text' => ['default' => 'local-model']],
            'headers' => ['X-Tenant-Id' => '7'],
        ])]);

        $request = $this->history[0]['request'];
        $this->assertSame('http://localhost:1234/v1/chat/completions', (string) $request->getUri());
        $this->assertSame('Bearer local-key', $request->getHeaderLine('Authorization'));
        $this->assertSame('7', $request->getHeaderLine('X-Tenant-Id'));
        $this->assertSame([
            'model' => 'local-model',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a sales coach.'],
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ], $this->sentBody());

        $this->assertSame('Hello.', $response->text);
        $this->assertSame(12, $response->usage->inputTokens);
        $this->assertSame(4, $response->usage->outputTokens);
    }

    public function testAKeyIsOptionalButAUrlAndModelAreRequired(): void
    {
        $this->queueJson($this->completion(['content' => 'ok']));
        (new SalesCoach())->prompt('Hi', provider: [
            Ai::build(['driver' => 'openai-compatible', 'url' => 'http://localhost:1234/v1']),
        ], model: 'm');
        $this->assertFalse($this->history[0]['request']->hasHeader('Authorization'));

        try {
            (new SalesCoach())->prompt('Hi', provider: [
                Ai::build(['driver' => 'openai-compatible', 'url' => 'http://localhost:1234/v1']),
            ]);
            $this->fail('Expected a missing-model exception.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no default text model', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a "url"');
        (new SalesCoach())->prompt('Hi', provider: [Ai::build(['driver' => 'openai-compatible'])], model: 'm');
    }

    public function testOllamaDefaultsToTheLocalServer(): void
    {
        $this->queueJson($this->completion(['content' => 'ok']));

        (new SalesCoach())->prompt('Hi', provider: 'ollama');

        $this->assertSame('http://localhost:11434/v1/chat/completions', (string) $this->history[0]['request']->getUri());
        $this->assertSame('qwen3.5:4b', $this->sentBody()['model']);
    }

    public function testToolRoundTrip(): void
    {
        $this->queueJson($this->completion([
            'content' => null,
            'tool_calls' => [[
                'id' => 'call_1',
                'type' => 'function',
                'function' => ['name' => 'RandomNumberGenerator', 'arguments' => '{"min":1,"max":6}'],
            ]],
        ], 'tool_calls'));
        $this->queueJson($this->completion(['content' => 'It is 6.']));

        $response = (new ToolAgent([new RandomNumberGenerator()]))->prompt('Roll', provider: 'ollama');

        $first = $this->sentBody(0);
        $this->assertSame('auto', $first['tool_choice']);
        $this->assertSame('RandomNumberGenerator', $first['tools'][0]['function']['name']);
        $this->assertSame(['min', 'max'], $first['tools'][0]['function']['parameters']['required']);

        $messages = $this->sentBody(1)['messages'];
        $this->assertSame([
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call_1',
                'type' => 'function',
                'function' => ['name' => 'RandomNumberGenerator', 'arguments' => '{"min":1,"max":6}'],
            ]],
        ], $messages[2]);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '6'], $messages[3]);
        $this->assertSame('It is 6.', $response->text);
    }

    public function testStructuredOutputAndSamplingOptions(): void
    {
        $this->queueJson($this->completion(['content' => '{"feedback":"Good","score":8}']));

        $agent = new #[Temperature(0.2)] class extends ScoringAgent {
        };

        $response = $agent->prompt('Score', provider: 'ollama');

        $body = $this->sentBody();
        $this->assertSame('json_schema', $body['response_format']['type']);
        $this->assertSame('schema_definition', $body['response_format']['json_schema']['name']);
        $this->assertSame(2048, $body['max_tokens']);
        $this->assertSame(0.2, $body['temperature']);
        $this->assertSame('claude-haiku-5-5', $body['model'], 'The #[Model] attribute still applies.');
        $this->assertSame(8, $response['score']);
    }
}
