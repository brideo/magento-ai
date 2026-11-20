<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Gateway\Gemini;

use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Exceptions\InvalidApiKeyException;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Test\Unit\AiTestCase;
use UpturnStudio\Ai\Test\Unit\Fixtures\RandomNumberGenerator;
use UpturnStudio\Ai\Test\Unit\Fixtures\SalesCoach;
use UpturnStudio\Ai\Test\Unit\Fixtures\ScoringAgent;
use UpturnStudio\Ai\Test\Unit\Fixtures\ToolAgent;

/**
 * @covers \UpturnStudio\Ai\Gateway\Gemini\GeminiGateway
 * @covers \UpturnStudio\Ai\Model\Provider\GeminiProvider
 */
class GeminiGatewayTest extends AiTestCase
{
    private function gemini(): ProviderInterface
    {
        return Ai::build(['driver' => 'gemini', 'key' => 'g-key']);
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @return array<string, mixed>
     */
    private function interaction(array $steps, string $status = 'completed'): array
    {
        return [
            'id' => 'int_1',
            'status' => $status,
            'model' => 'gemini-3.8-flash',
            'steps' => $steps,
            'usage' => ['total_input_tokens' => 30, 'total_output_tokens' => 6, 'total_thought_tokens' => 4, 'total_cached_tokens' => 10],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelOutput(string $text): array
    {
        return ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]];
    }

    public function testSendsAnInteractionsRequest(): void
    {
        $this->queueJson($this->interaction([$this->modelOutput('Great call.')]));

        $response = (new SalesCoach())->prompt('Analyze this', provider: [$this->gemini()]);

        $request = $this->history[0]['request'];
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/interactions', (string) $request->getUri());
        $this->assertSame('g-key', $request->getHeaderLine('x-goog-api-key'));
        $this->assertSame([
            'model' => 'gemini-3.8-flash',
            'input' => [['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'Analyze this']]]],
            'store' => false,
            'system_instruction' => 'You are a sales coach.',
        ], $this->sentBody());

        $this->assertSame('Great call.', $response->text);
        $this->assertSame(FinishReason::Stop, $response->finishReason);
        $this->assertSame(30, $response->usage->inputTokens);
        $this->assertSame(10, $response->usage->outputTokens, 'Thought tokens count as output.');
        $this->assertSame(4, $response->usage->reasoningTokens);
        $this->assertSame(10, $response->usage->cacheReadInputTokens);
    }

    public function testToolRoundTripReplaysStepsAndSendsFunctionResults(): void
    {
        $thought = ['type' => 'thought', 'signature' => 'sig_1', 'summary' => [['type' => 'text', 'text' => 'Roll it.']]];
        $call = ['type' => 'function_call', 'id' => 'fc_1', 'name' => 'RandomNumberGenerator', 'arguments' => ['min' => 1, 'max' => 6]];
        $this->queueJson($this->interaction([$thought, $call], 'requires_action'));
        $this->queueJson($this->interaction([$this->modelOutput('It is 6.')]));

        $response = (new ToolAgent([new RandomNumberGenerator()]))->prompt('Roll a die', provider: [$this->gemini()]);

        $tool = $this->sentBody(0)['tools'][0];
        $this->assertSame('function', $tool['type']);
        $this->assertSame('RandomNumberGenerator', $tool['name']);
        $this->assertArrayNotHasKey('additionalProperties', $tool['parameters']);
        $this->assertSame(['min', 'max'], $tool['parameters']['required']);

        $input = $this->sentBody(1)['input'];
        $this->assertSame($thought, $input[1]);
        $this->assertSame($call, $input[2]);
        $this->assertSame([
            'type' => 'function_result',
            'name' => 'RandomNumberGenerator',
            'call_id' => 'fc_1',
            'result' => [['type' => 'text', 'text' => '6']],
        ], $input[3]);

        $this->assertSame('It is 6.', $response->text);
        $this->assertSame('Roll it.', $response->reasoning);
    }

    public function testStructuredOutputAndGenerationConfig(): void
    {
        $this->queueJson($this->interaction([$this->modelOutput('{"feedback":"Good","score":8}')]));

        $response = (new ScoringAgent())->prompt('Score', provider: [$this->gemini()], model: 'gemini-3.1-flash-lite');

        $body = $this->sentBody();
        $this->assertSame('gemini-3.1-flash-lite', $body['model']);
        $this->assertSame('application/json', $body['response_format']['mime_type']);
        $this->assertSame(['feedback', 'score'], $body['response_format']['schema']['required']);
        $this->assertSame(['max_output_tokens' => 2048], $body['generation_config']);
        $this->assertSame(8, $response['score']);
    }

    public function testBlockedInteractionsFinishAsContentFilterAndFailuresThrow(): void
    {
        $blocked = $this->interaction([], 'failed');
        $blocked['errors'] = [['code' => 'SAFETY', 'message' => 'Blocked by safety settings']];
        $this->queueJson($blocked);

        $provider = $this->gemini();
        $this->assertSame(
            FinishReason::ContentFilter,
            (new SalesCoach())->prompt('Hi', provider: [$provider])->finishReason
        );

        $failed = $this->interaction([], 'failed');
        $failed['errors'] = [['code' => 'INTERNAL', 'message' => 'Backend exploded']];
        $this->queueJson($failed);

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('Backend exploded');
        (new SalesCoach())->prompt('Hi', provider: [$provider]);
    }

    public function testAnInvalidKeyReportedAs400IsRecognised(): void
    {
        $this->queueJson(['error' => [
            'code' => 400,
            'message' => 'API key not valid. Please pass a valid API key.',
            'details' => [['reason' => 'API_KEY_INVALID']],
        ]], 400);

        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('API key not valid');
        (new SalesCoach())->prompt('Hi', provider: [$this->gemini()]);
    }
}
