<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\OpenAiCompatible;

use InvalidArgumentException;
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
 * The wire adapter for any endpoint that speaks OpenAI's chat completions format: Ollama, LM Studio, vLLM,
 * OpenRouter, Groq, Together and local gateways.
 */
class OpenAiCompatibleGateway implements TextGatewayInterface
{
    use HandlesFailoverErrors;
    use InteractsWithProvider;

    /**
     * @param Client $http
     * @param string|null $defaultUrl Used when the provider has no "url" configured.
     */
    public function __construct(
        private readonly Client $http,
        private readonly ?string $defaultUrl = null
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
        $url = $this->endpoint($provider, $this->defaultUrl);

        if ($url === null) {
            throw new InvalidArgumentException(
                sprintf('The [%s] openai-compatible provider requires a "url" to be configured.', $provider->name())
            );
        }

        $headers = $this->configuredHeaders($provider);
        $key = $provider->credentials()['key'];
        if ($key !== null) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        $body = $this->buildRequest($model, $instructions, $messages, $tools, $schema, $options);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn (): Response => $this->http->postJson($url . '/chat/completions', $headers, $body, $timeout ?? 60)
        );

        return $this->parseResponse($response, $provider, $schema);
    }

    /**
     * Build the chat completions request body.
     *
     * @param string $model
     * @param string|null $instructions
     * @param Message[] $messages
     * @param array<int,object> $tools
     * @param array<string,mixed>|null $schema
     * @param TextGenerationOptions $options
     * @return array<string,mixed>
     */
    public function buildRequest(
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options
    ): array {
        $body = ['model' => $model];

        $mappedTools = array_map(static fn (array $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['schema'],
            ],
        ], $this->functionTools($tools));

        if ($mappedTools !== []) {
            $body['tool_choice'] = 'auto';
            $body['tools'] = $mappedTools;
        }

        $body['messages'] = $this->mapMessages($messages, $instructions);

        if ($schema !== null) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'schema_definition', 'schema' => $schema, 'strict' => $options->strict],
            ];
        }

        $body += $this->withoutNulls([
            'max_tokens' => $options->maxTokens,
            'temperature' => $options->temperature,
            'top_p' => $options->topP,
        ]);

        return array_replace_recursive($body, $options->providerOptions);
    }

    /**
     * Map SDK messages onto chat messages.
     *
     * @param Message[] $messages
     * @param string|null $instructions
     * @return array<int,array<string,mixed>>
     */
    private function mapMessages(array $messages, ?string $instructions): array
    {
        $chat = [];

        if ($instructions !== null && $instructions !== '') {
            $chat[] = ['role' => 'system', 'content' => $instructions];
        }

        foreach ($messages as $message) {
            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $result) {
                    $chat[] = ['role' => 'tool', 'tool_call_id' => $result->id, 'content' => $result->result];
                }
                continue;
            }

            if ($message->role !== MessageRole::Assistant) {
                $chat[] = ['role' => 'user', 'content' => $message->content];
                continue;
            }

            $entry = ['role' => 'assistant'];
            if ($message->content !== '') {
                $entry['content'] = $message->content;
            }
            if ($message instanceof AssistantMessage && $message->toolCalls !== []) {
                $entry['tool_calls'] = array_map(static fn (ToolCall $toolCall): array => [
                    'id' => $toolCall->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $toolCall->name,
                        'arguments' => (string) json_encode($toolCall->arguments ?: new \stdClass()),
                    ],
                ], $message->toolCalls);
            }
            $chat[] = $entry;
        }

        return $chat;
    }

    /**
     * Parse a chat completions response.
     *
     * @param Response $response
     * @param ProviderInterface $provider
     * @param array<string,mixed>|null $schema
     * @throws AiException When the endpoint reports an error inside a successful HTTP response.
     */
    public function parseResponse(Response $response, ProviderInterface $provider, ?array $schema): StepResponse
    {
        $data = (array) $response->json();

        if ($data === [] || isset($data['error'])) {
            throw new AiException(sprintf(
                'OpenAI-compatible error: [%s] %s',
                $data['error']['type'] ?? 'unknown',
                $data['error']['message'] ?? 'Unknown error.'
            ));
        }

        $choice = (array) ($data['choices'][0] ?? []);
        $message = (array) ($choice['message'] ?? []);
        $text = (string) ($message['content'] ?? '');

        $toolCalls = array_map(static fn (array $toolCall): ToolCall => new ToolCall(
            (string) ($toolCall['id'] ?? ''),
            (string) ($toolCall['function']['name'] ?? ''),
            (array) (json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true) ?? [])
        ), (array) ($message['tool_calls'] ?? []));

        $finishReason = match ($choice['finish_reason'] ?? '') {
            'stop' => FinishReason::Stop,
            'tool_calls' => FinishReason::ToolCalls,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default => FinishReason::Unknown,
        };

        if ($toolCalls !== [] && $finishReason === FinishReason::Stop) {
            $finishReason = FinishReason::ToolCalls;
        }

        $usage = (array) ($data['usage'] ?? []);

        return new StepResponse(
            $text,
            $toolCalls,
            $finishReason,
            new TextUsage(
                (int) ($usage['prompt_tokens'] ?? 0),
                (int) ($usage['completion_tokens'] ?? 0),
                $usage['prompt_tokens_details']['cached_tokens'] ?? null,
                null,
                $usage['completion_tokens_details']['reasoning_tokens'] ?? null
            ),
            new Meta($provider->name(), (string) ($data['model'] ?? '')),
            $this->decodeStructured($schema, $text),
            (string) ($message['reasoning_content'] ?? $message['reasoning'] ?? ''),
            [],
            $response
        );
    }
}
