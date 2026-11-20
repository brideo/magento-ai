<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\Gemini;

use UpturnStudio\Ai\Api\Gateway\TextGatewayInterface;
use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Exceptions\AiException;
use UpturnStudio\Ai\Gateway\Concerns\HandlesFailoverErrors;
use UpturnStudio\Ai\Gateway\Concerns\InteractsWithProvider;
use UpturnStudio\Ai\Gateway\StepResponse;
use UpturnStudio\Ai\Gateway\TextGenerationOptions;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Http\RequestException;
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
 * The wire adapter for Google Gemini's Interactions API. Interactions are sent with "store" off, so the full
 * conversation travels with every request.
 */
class GeminiGateway implements TextGatewayInterface
{
    use HandlesFailoverErrors;
    use InteractsWithProvider;

    public const DEFAULT_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Provider options that belong at the top of the request rather than in generation_config.
     */
    private const TOP_LEVEL_OPTIONS = [
        'agent', 'agent_config', 'background', 'environment', 'labels', 'previous_interaction_id',
        'response_format', 'safety_settings', 'service_tier', 'store', 'user_metadata', 'webhook_config',
    ];

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

        $headers = $this->configuredHeaders($provider);
        $key = $provider->credentials()['key'];
        if ($key !== null) {
            $headers['x-goog-api-key'] = $key;
        }

        $response = $this->withErrorHandling(
            $provider->name(),
            fn (): Response => $this->http->postJson(
                $this->endpoint($provider, self::DEFAULT_URL) . '/interactions',
                $headers,
                $body,
                $timeout ?? 60
            )
        );

        return $this->parseResponse($response, $provider, $model, $schema);
    }

    /**
     * Build the Interactions API request body.
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
        $body = ['model' => $model, 'input' => $this->mapMessages($messages), 'store' => false];

        if ($instructions !== null && $instructions !== '') {
            $body['system_instruction'] = $instructions;
        }

        $mappedTools = array_map(fn (array $tool): array => [
            'type' => 'function',
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => $this->toGeminiSchema($tool['schema']),
        ], $this->functionTools($tools));

        if ($mappedTools !== []) {
            $body['tools'] = $mappedTools;
        }

        if ($schema !== null) {
            $body['response_format'] = ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema];
        }

        $generationConfig = $this->withoutNulls([
            'max_output_tokens' => $options->maxTokens,
            'temperature' => $options->temperature,
            'top_p' => $options->topP,
        ]);

        foreach ($options->providerOptions as $key => $value) {
            if (in_array($key, ['generation_config', 'generationConfig'], true) && is_array($value)) {
                $generationConfig = array_replace($generationConfig, $value);
            } elseif (in_array($key, self::TOP_LEVEL_OPTIONS, true)) {
                $body[$key] = $value;
            } else {
                $generationConfig[$key] = $value;
            }
        }

        if ($generationConfig !== []) {
            $body['generation_config'] = $generationConfig;
        }

        return $body;
    }

    /**
     * Map SDK messages onto interaction steps.
     *
     * @param Message[] $messages
     * @return array<int,array<string,mixed>>
     */
    private function mapMessages(array $messages): array
    {
        $input = [];

        foreach ($messages as $message) {
            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $result) {
                    $input[] = [
                        'type' => 'function_result',
                        'name' => $result->name,
                        'call_id' => $result->id,
                        'result' => [['type' => 'text', 'text' => $result->result]],
                    ];
                }
                continue;
            }

            if ($message->role !== MessageRole::Assistant) {
                $input[] = [
                    'type' => 'user_input',
                    'content' => $message->content === '' ? [] : [['type' => 'text', 'text' => $message->content]],
                ];
                continue;
            }

            if ($message instanceof AssistantMessage && $message->replayBlocks !== []) {
                foreach ($message->replayBlocks as $step) {
                    // Function-call arguments must stay a JSON object, even when empty.
                    if (array_key_exists('arguments', $step) && is_array($step['arguments'])) {
                        $step['arguments'] = (object) $step['arguments'];
                    }
                    $input[] = $step;
                }
                continue;
            }

            if ($message->content !== '') {
                $input[] = ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $message->content]]];
            }

            if ($message instanceof AssistantMessage) {
                foreach ($message->toolCalls as $toolCall) {
                    $input[] = [
                        'type' => 'function_call',
                        'id' => $toolCall->id,
                        'name' => $toolCall->name,
                        'arguments' => (object) $toolCall->arguments,
                    ];
                }
            }
        }

        return $input;
    }

    /**
     * Adapt a JSON schema for Gemini function parameters.
     *
     * Gemini takes no additionalProperties, and "nullable" in place of a null type.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function toGeminiSchema(array $schema): array
    {
        unset($schema['additionalProperties']);

        if (is_array($schema['type'] ?? null) && in_array('null', $schema['type'], true)) {
            $remaining = array_values(array_diff($schema['type'], ['null']));
            if (count($remaining) === 1) {
                $schema['type'] = $remaining[0];
                $schema['nullable'] = true;
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                $schema['properties'][$name] = $this->toGeminiSchema((array) $property);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->toGeminiSchema($schema['items']);
        }

        return $schema;
    }

    /**
     * Parse an Interactions API response.
     *
     * @param Response $response
     * @param ProviderInterface $provider
     * @param string $model
     * @param array<string,mixed>|null $schema
     * @throws AiException When the API reports an error inside a successful HTTP response.
     */
    public function parseResponse(
        Response $response,
        ProviderInterface $provider,
        string $model,
        ?array $schema
    ): StepResponse {
        $data = (array) $response->json();
        $errors = (array) ($data['errors'] ?? []);
        $blocked = $this->wasBlocked($errors);

        if ($data === [] || isset($data['error'])) {
            throw new AiException(sprintf(
                'Gemini error: [%s] %s',
                $data['error']['code'] ?? 'unknown',
                $data['error']['message'] ?? 'Unknown Gemini error.'
            ));
        }

        if (in_array($data['status'] ?? '', ['failed', 'cancelled'], true) && !$blocked) {
            throw new AiException(sprintf(
                'Gemini error: [%s] %s',
                $errors[0]['code'] ?? $data['status'],
                $errors[0]['message'] ?? 'The Gemini interaction did not complete.'
            ));
        }

        $steps = array_values(array_filter((array) ($data['steps'] ?? []), 'is_array'));

        $text = '';
        $reasoning = [];
        $toolCalls = [];
        $replay = [];
        $needsReplay = false;

        foreach ($steps as $index => $step) {
            $type = (string) ($step['type'] ?? '');

            if (in_array($type, ['user_input', 'function_result'], true)) {
                continue;
            }

            if ($type === 'model_output') {
                $text .= $this->textOf($step);
            } elseif ($type === 'thought') {
                $needsReplay = true;
                $reasoning[] = $this->textOf($step);
            } elseif ($type === 'function_call') {
                $needsReplay = true;
                // Gemini may omit the id; the result has to reference something stable.
                $step['id'] = (string) ($step['id'] ?? 'call_' . $index);
                $arguments = $step['arguments'] ?? [];
                $toolCalls[] = new ToolCall(
                    $step['id'],
                    (string) ($step['name'] ?? ''),
                    is_array($arguments) ? $arguments : (array) (json_decode((string) $arguments, true) ?? [])
                );
            } else {
                $needsReplay = true;
            }

            $replay[] = $step;
        }

        $finishReason = match (true) {
            $toolCalls !== [] => FinishReason::ToolCalls,
            $blocked => FinishReason::ContentFilter,
            default => match ($data['status'] ?? '') {
                'completed' => FinishReason::Stop,
                'incomplete' => FinishReason::Length,
                'requires_action' => FinishReason::ToolCalls,
                default => FinishReason::Unknown,
            },
        };

        $usage = (array) ($data['usage'] ?? []);
        $thoughtTokens = $usage['total_thought_tokens'] ?? null;

        return new StepResponse(
            $text,
            $toolCalls,
            $finishReason,
            new TextUsage(
                (int) ($usage['total_input_tokens'] ?? 0),
                (int) ($usage['total_output_tokens'] ?? 0) + (int) $thoughtTokens,
                $usage['total_cached_tokens'] ?? null,
                null,
                $thoughtTokens
            ),
            new Meta($provider->name(), (string) ($data['model'] ?? $model)),
            $this->decodeStructured($schema, $text),
            implode("\n\n", array_filter($reasoning)),
            $needsReplay ? $replay : [],
            $response
        );
    }

    /**
     * The text of a step, from its summary or content blocks.
     *
     * @param array<string,mixed> $step
     */
    private function textOf(array $step): string
    {
        $text = '';
        foreach ((array) ($step['summary'] ?? $step['content'] ?? []) as $block) {
            if (is_array($block) && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    /**
     * Whether the interaction was stopped by a safety filter.
     *
     * @param array<int,array<string,mixed>> $errors
     */
    private function wasBlocked(array $errors): bool
    {
        foreach ($errors as $error) {
            $haystack = strtolower(($error['code'] ?? '') . ' ' . ($error['message'] ?? ''));
            foreach (['safety', 'blocked', 'blocklist', 'prohibited', 'recitation'] as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gemini reports a bad key as 400 or 403 as well as 401.
     *
     * @param RequestException $e
     */
    protected function isInvalidApiKey(RequestException $e): bool
    {
        $error = (array) ($e->response->json('0.error') ?? $e->response->json('error') ?? []);
        $message = (string) ($error['message'] ?? '');

        return match ($e->response->status()) {
            401 => true,
            400 => in_array('API_KEY_INVALID', array_column((array) ($error['details'] ?? []), 'reason'), true),
            403 => str_contains($message, 'unregistered callers') || str_contains($message, 'reported as leaked'),
            default => false,
        };
    }

    /**
     * Gemini wraps some errors in a one-element list.
     *
     * @param RequestException $e
     */
    protected function errorMessage(RequestException $e): string
    {
        $message = $e->response->json('error.message') ?? $e->response->json('0.error.message');

        return is_string($message) && $message !== '' ? $message : mb_substr($e->response->body(), 0, 300);
    }
}
