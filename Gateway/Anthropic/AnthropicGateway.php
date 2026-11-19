<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\Anthropic;

use stdClass;
use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Api\ToolInterface;
use UpturnStudio\Ai\Gateway\Concerns\HandlesFailoverErrors;
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
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Tools\ToolNameResolver;

/**
 * The wire adapter for Anthropic's Messages API.
 *
 * Sampling parameters (temperature, top_p) are not sent: API version 2026-10-21 rejects them on every model.
 */
class AnthropicGateway implements TextGatewayInterface
{
    use HandlesFailoverErrors;

    public const DEFAULT_URL = 'https://api.anthropic.com/v1';
    public const API_VERSION = '2026-10-21';
    public const DEFAULT_MAX_TOKENS = 16000;

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
        $body = $this->buildRequest($model, $instructions, $messages, $tools, $schema, $options);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn (): Response => $this->http->postJson(
                $this->baseUrl($provider) . '/messages',
                $this->headers($provider),
                $body,
                $timeout ?? 60
            )
        );

        return $this->parseResponse($response, $provider, $model, $schema);
    }

    /**
     * Build the Messages API request body.
     *
     * @param string $model
     * @param string|null $instructions
     * @param Message[] $messages
     * @param array<int,object> $tools
     * @param array<string,mixed>|null $schema
     * @param TextGenerationOptions $options
     * @return array<string, mixed>
     */
    public function buildRequest(
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options
    ): array {
        $body = [
            'model' => $model,
            'max_tokens' => $options->maxTokens ?? self::DEFAULT_MAX_TOKENS,
            'messages' => $this->mapMessages($messages),
        ];

        if ($instructions !== null && $instructions !== '') {
            $body['system'] = $options->cacheInstructions === null
                ? $instructions
                : [[
                    'type' => 'text',
                    'text' => $instructions,
                    'cache_control' => $this->cacheControl($options->cacheInstructions),
                ]];
        }

        $mappedTools = $this->mapTools($tools, $options);
        if ($mappedTools !== []) {
            $body['tools'] = $mappedTools;
        }

        if ($schema !== null) {
            $body['output_config'] = ['format' => [
                'type' => 'json_schema',
                'schema' => (new AnthropicSchemaSanitizer())->sanitize($schema),
            ]];
        }

        return array_replace_recursive($body, $options->providerOptions);
    }

    /**
     * Map SDK messages onto Anthropic's alternating user/assistant turns.
     *
     * @param Message[] $messages
     * @return array<int, array<string, mixed>>
     */
    private function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            if ($message instanceof ToolResultMessage) {
                $mapped[] = ['role' => 'user', 'content' => array_map(
                    static fn ($result): array => array_filter([
                        'type' => 'tool_result',
                        'tool_use_id' => $result->id,
                        'content' => $result->result,
                        'is_error' => $result->isError ?: null,
                    ], static fn ($value) => $value !== null),
                    $message->toolResults
                )];
                continue;
            }

            if ($message instanceof AssistantMessage) {
                $mapped[] = ['role' => 'assistant', 'content' => $this->mapAssistantContent($message)];
                continue;
            }

            $mapped[] = [
                'role' => $message->role === MessageRole::Assistant ? 'assistant' : 'user',
                'content' => $message->content,
            ];
        }

        return $mapped;
    }

    /**
     * The content blocks of an assistant turn.
     *
     * Blocks the API returned (thinking, server tool use) are replayed exactly as received, which Anthropic
     * requires; otherwise the turn is rebuilt from its text and tool calls.
     *
     * @param AssistantMessage $message
     * @return array<int, array<string, mixed>>|string
     */
    private function mapAssistantContent(AssistantMessage $message): array|string
    {
        if ($message->replayBlocks !== []) {
            return array_map(static function (array $block): array {
                // An empty tool input decodes to a PHP array and would re-encode as [], which the API rejects.
                if (($block['type'] ?? '') === 'tool_use' && ($block['input'] ?? []) === []) {
                    $block['input'] = new stdClass();
                }

                return $block;
            }, $message->replayBlocks);
        }

        if ($message->toolCalls === []) {
            return $message->content;
        }

        $blocks = $message->content === '' ? [] : [['type' => 'text', 'text' => $message->content]];

        foreach ($message->toolCalls as $toolCall) {
            $blocks[] = [
                'type' => 'tool_use',
                'id' => $toolCall->id,
                'name' => $toolCall->name,
                'input' => $toolCall->arguments === [] ? new stdClass() : $toolCall->arguments,
            ];
        }

        return $blocks;
    }

    /**
     * Map SDK tools onto Anthropic tool definitions.
     *
     * @param array<int,object> $tools
     * @param TextGenerationOptions $options
     * @return array<int, array<string, mixed>>
     */
    private function mapTools(array $tools, TextGenerationOptions $options): array
    {
        $mapped = [];

        foreach ($tools as $tool) {
            if (!$tool instanceof ToolInterface) {
                continue;
            }

            $definition = [
                'name' => ToolNameResolver::resolve($tool),
                'description' => (string) $tool->description(),
                'input_schema' => JsonSchema::fromProperties($tool->schema(new JsonSchema())),
            ];

            if ($options->strict) {
                $definition['strict'] = true;
                $definition['input_schema'] = (new AnthropicSchemaSanitizer())->sanitize($definition['input_schema']);
            }

            $mapped[] = $definition;
        }

        if ($mapped !== [] && $options->cacheToolDefinitions !== null) {
            $mapped[count($mapped) - 1]['cache_control'] = $this->cacheControl($options->cacheToolDefinitions);
        }

        return $mapped;
    }

    /**
     * Parse a Messages API response.
     *
     * @param Response $response
     * @param ProviderInterface $provider
     * @param string $model
     * @param array<string,mixed>|null $schema
     */
    public function parseResponse(
        Response $response,
        ProviderInterface $provider,
        string $model,
        ?array $schema
    ): StepResponse {
        $data = (array) $response->json();
        $content = (array) ($data['content'] ?? []);

        $text = '';
        $reasoning = '';
        $toolCalls = [];
        $needsReplay = false;

        foreach ($content as $block) {
            switch ($block['type'] ?? '') {
                case 'text':
                    $text .= $block['text'] ?? '';
                    break;
                case 'thinking':
                    $reasoning .= $block['thinking'] ?? '';
                    $needsReplay = true;
                    break;
                case 'tool_use':
                    $toolCalls[] = new ToolCall(
                        (string) $block['id'],
                        (string) $block['name'],
                        (array) ($block['input'] ?? [])
                    );
                    break;
                default:
                    // Redacted thinking, server tool use and their results must go back unchanged.
                    $needsReplay = true;
            }
        }

        $usage = (array) ($data['usage'] ?? []);
        $cacheRead = $usage['cache_read_input_tokens'] ?? null;
        $cacheWrite = $usage['cache_creation_input_tokens'] ?? null;

        $structured = null;
        if ($schema !== null && $text !== '') {
            $decoded = json_decode($text, true);
            $structured = is_array($decoded) ? $decoded : null;
        }

        return new StepResponse(
            $text,
            $toolCalls,
            $this->mapFinishReason((string) ($data['stop_reason'] ?? '')),
            new TextUsage(
                // Anthropic reports uncached input separately; the SDK's inputTokens is the total.
                (int) ($usage['input_tokens'] ?? 0) + (int) $cacheRead + (int) $cacheWrite,
                (int) ($usage['output_tokens'] ?? 0),
                $cacheRead === null ? null : (int) $cacheRead,
                $cacheWrite === null ? null : (int) $cacheWrite
            ),
            new Meta($provider->name(), (string) ($data['model'] ?? $model)),
            $structured,
            $reasoning,
            $needsReplay ? $content : [],
            $response
        );
    }

    /**
     * Map Anthropic's stop_reason onto the SDK's finish reasons.
     *
     * @param string $stopReason
     */
    private function mapFinishReason(string $stopReason): FinishReason
    {
        return match ($stopReason) {
            'end_turn', 'stop_sequence' => FinishReason::Stop,
            'tool_use' => FinishReason::ToolCalls,
            'max_tokens', 'model_context_window_exceeded' => FinishReason::Length,
            'refusal' => FinishReason::Refusal,
            'pause_turn' => FinishReason::Continue,
            default => FinishReason::Unknown,
        };
    }

    /**
     * A cache_control value for a lifetime of "5m" or "1h".
     *
     * @param string $ttl
     * @return array<string, string>
     */
    private function cacheControl(string $ttl): array
    {
        return $ttl === '1h' ? ['type' => 'ephemeral', 'ttl' => '1h'] : ['type' => 'ephemeral'];
    }

    /**
     * The API base URL, honouring a configured proxy or gateway URL.
     *
     * @param ProviderInterface $provider
     */
    private function baseUrl(ProviderInterface $provider): string
    {
        $url = $provider->additionalConfiguration()['url'] ?? null;

        return rtrim(is_string($url) && $url !== '' ? $url : self::DEFAULT_URL, '/');
    }

    /**
     * The request headers, including any configured on the provider.
     *
     * @param ProviderInterface $provider
     * @return array<string, string>
     */
    private function headers(ProviderInterface $provider): array
    {
        return array_merge(
            (array) ($provider->additionalConfiguration()['headers'] ?? []),
            [
                'x-api-key' => (string) $provider->credentials()['key'],
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function insufficientCreditPatterns(): array
    {
        return ['credit balance is too low'];
    }
}
