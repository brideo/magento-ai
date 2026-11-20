<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\OpenAi;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Gateway\Concerns\HandlesFailoverErrors;
use UpturnStudio\Ai\Gateway\Concerns\InteractsWithProvider;
use UpturnStudio\Ai\Gateway\StepResponse;
use UpturnStudio\Ai\Gateway\TextGenerationOptions;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Http\Response;
use UpturnStudio\Ai\Messages\AssistantMessage;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Messages\MessageRole;
use UpturnStudio\Ai\Messages\ToolResultMessage;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Responses\Data\Meta;
use UpturnStudio\Ai\Responses\Data\TextUsage;
use UpturnStudio\Ai\Responses\Data\ToolCall;

/**
 * The wire adapter for OpenAI's Responses API.
 *
 * Set "store" to false in the provider configuration to stop OpenAI retaining responses; reasoning is then
 * carried between steps as encrypted content.
 */
class OpenAiGateway implements TextGatewayInterface
{
    use HandlesFailoverErrors;
    use InteractsWithProvider;

    public const DEFAULT_URL = 'https://api.openai.com/v1';

    /**
     * @param Client $http
     */
    public function __construct(
        private readonly Client $http
    ) {
    }

    /**
     * @inheritDoc
     */
    public function generateTextStep(
        TextProviderInterface $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options,
        ?int $timeout = null
    ): StepResponse {
        $body = $this->buildRequest($provider, $model, $instructions, $messages, $tools, $schema, $options);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn (): Response => $this->http->postJson(
                $this->endpoint($provider, self::DEFAULT_URL) . '/responses',
                array_merge($this->configuredHeaders($provider), [
                    'Authorization' => 'Bearer ' . $provider->credentials()['key'],
                ]),
                $body,
                $timeout ?? 60
            )
        );

        return $this->parseResponse($response, $provider, $schema);
    }

    /**
     * Build the Responses API request body.
     *
     * @param ProviderInterface $provider
     * @param string $model
     * @param string|null $instructions
     * @param Message[] $messages
     * @param array<int,object> $tools
     * @param array<string,mixed>|null $schema
     * @param TextGenerationOptions $options
     * @return array<string,mixed>
     */
    public function buildRequest(
        ProviderInterface $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options
    ): array {
        $body = ['model' => $model, 'input' => $this->mapMessages($messages, $instructions)];

        $mappedTools = array_map(fn (array $tool): array => [
            'type' => 'function',
            'name' => $tool['name'],
            'description' => $tool['description'],
            'strict' => $options->strict,
            'parameters' => $tool['schema'],
        ], $this->functionTools($tools));

        if ($mappedTools !== []) {
            $body['tool_choice'] = 'auto';
            $body['tools'] = $mappedTools;
        }

        if ($schema !== null) {
            $body['text'] = ['format' => [
                'type' => 'json_schema',
                'name' => 'schema_definition',
                'schema' => $schema,
                'strict' => $options->strict,
            ]];
        }

        $body += $this->withoutNulls([
            'max_output_tokens' => $options->maxTokens,
            'temperature' => $options->temperature,
            'top_p' => $options->topP,
        ]);

        $body = array_replace_recursive($body, $options->providerOptions);

        $stateless = filter_var(
            $provider->additionalConfiguration()['store'] ?? true,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) === false;

        if ($stateless) {
            $body['store'] = false;
        }

        if ($this->isReasoningModel($model)) {
            $body['include'] = array_values(array_unique(
                [...($body['include'] ?? []), 'reasoning.encrypted_content']
            ));
        }

        return $body;
    }

    /**
     * Map SDK messages onto Responses API input items.
     *
     * @param Message[] $messages
     * @param string|null $instructions
     * @return array<int,array<string,mixed>>
     */
    private function mapMessages(array $messages, ?string $instructions): array
    {
        $input = [];

        if ($instructions !== null && $instructions !== '') {
            $input[] = ['role' => 'system', 'content' => $instructions];
        }

        foreach ($messages as $message) {
            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $result) {
                    $input[] = [
                        'type' => 'function_call_output',
                        'call_id' => $result->id,
                        'output' => $result->result,
                    ];
                }
                continue;
            }

            if ($message->role === MessageRole::Assistant) {
                array_push($input, ...$this->mapAssistantMessage($message));
                continue;
            }

            $input[] = ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $message->content]]];
        }

        return $input;
    }

    /**
     * The input items for an assistant turn.
     *
     * The provider's own output items are replayed when we have them, because they carry reasoning that must
     * be returned with tool calls. Otherwise the turn is rebuilt from its text and tool calls.
     *
     * @param Message $message
     * @return array<int,array<string,mixed>>
     */
    private function mapAssistantMessage(Message $message): array
    {
        if ($message instanceof AssistantMessage && $message->replayBlocks !== []) {
            return $message->replayBlocks;
        }

        $items = [];

        if ($message instanceof AssistantMessage) {
            foreach ($message->toolCalls as $toolCall) {
                $items[] = [
                    'type' => 'function_call',
                    'call_id' => $toolCall->id,
                    'name' => $toolCall->name,
                    'arguments' => (string) json_encode($toolCall->arguments ?: new \stdClass()),
                ];
            }
        }

        if ($message->content !== '') {
            $items[] = [
                'role' => 'assistant',
                'content' => [['type' => 'output_text', 'text' => $message->content]],
            ];
        }

        return $items;
    }

    /**
     * Parse a Responses API response.
     *
     * @param Response $response
     * @param ProviderInterface $provider
     * @param array<string,mixed>|null $schema
     * @throws AiException When the API reports an error inside a successful HTTP response.
     */
    public function parseResponse(Response $response, ProviderInterface $provider, ?array $schema): StepResponse
    {
        $data = (array) $response->json();

        if ($data === [] || isset($data['error']) || ($data['status'] ?? '') === 'failed') {
            throw new AiException(sprintf(
                'OpenAI error: [%s] %s',
                $data['error']['code'] ?? $data['error']['type'] ?? 'unknown',
                $data['error']['message'] ?? 'The response failed without an error message.'
            ));
        }

        $output = array_values(array_filter((array) ($data['output'] ?? []), 'is_array'));

        $text = '';
        $reasoning = [];
        $toolCalls = [];
        $needsReplay = false;

        foreach ($output as $item) {
            switch ($item['type'] ?? '') {
                case 'message':
                    foreach ((array) ($item['content'] ?? []) as $part) {
                        if (($part['type'] ?? '') === 'output_text') {
                            $text .= $part['text'] ?? '';
                        }
                    }
                    break;
                case 'function_call':
                    $needsReplay = true;
                    $toolCalls[] = new ToolCall(
                        (string) ($item['call_id'] ?? $item['id'] ?? ''),
                        (string) ($item['name'] ?? ''),
                        (array) (json_decode((string) ($item['arguments'] ?? '{}'), true) ?? [])
                    );
                    break;
                case 'reasoning':
                    $needsReplay = true;
                    foreach ([...(array) ($item['summary'] ?? []), ...(array) ($item['content'] ?? [])] as $part) {
                        $reasoning[] = (string) ($part['text'] ?? '');
                    }
                    break;
                default:
                    $needsReplay = true;
            }
        }

        $usage = (array) ($data['usage'] ?? []);

        return new StepResponse(
            $text,
            $toolCalls,
            $this->finishReason($data, $output),
            new TextUsage(
                (int) ($usage['input_tokens'] ?? 0),
                (int) ($usage['output_tokens'] ?? 0),
                $usage['input_tokens_details']['cached_tokens'] ?? null,
                $usage['input_tokens_details']['cache_write_tokens'] ?? null,
                $usage['output_tokens_details']['reasoning_tokens'] ?? null
            ),
            new Meta($provider->name(), (string) ($data['model'] ?? '')),
            $this->decodeStructured($schema, $text),
            implode("\n\n", array_filter($reasoning)),
            $needsReplay ? $output : [],
            $response
        );
    }

    /**
     * Work out why the model stopped.
     *
     * @param array<string,mixed> $data
     * @param array<int,array<string,mixed>> $output
     */
    private function finishReason(array $data, array $output): FinishReason
    {
        if (($data['status'] ?? '') === 'incomplete') {
            return match ($data['incomplete_details']['reason'] ?? null) {
                'max_output_tokens' => FinishReason::Length,
                'content_filter' => FinishReason::ContentFilter,
                default => FinishReason::Unknown,
            };
        }

        $last = $output === [] ? [] : $output[count($output) - 1];
        $type = (string) ($last['type'] ?? '');

        return match ($last['status'] ?? $data['status'] ?? '') {
            'incomplete' => FinishReason::Length,
            'failed' => FinishReason::Error,
            'completed' => match (true) {
                $type === 'message' => FinishReason::Stop,
                str_ends_with($type, '_call') => FinishReason::ToolCalls,
                default => FinishReason::Unknown,
            },
            default => FinishReason::Unknown,
        };
    }

    /**
     * Whether a model reasons before answering, so its reasoning has to be carried between steps.
     *
     * @param string $model
     */
    private function isReasoningModel(string $model): bool
    {
        if (str_starts_with($model, 'gpt-5-chat')) {
            return false;
        }

        foreach (['gpt-5', 'gpt-6', 'o1', 'o3', 'o4-mini'] as $prefix) {
            if (str_starts_with($model, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
