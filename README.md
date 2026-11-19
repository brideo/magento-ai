# UpturnStudio_Ai

An AI SDK for Magento 2 and Mage-OS, modelled on the [Laravel AI SDK](https://laravel.com/docs/ai-sdk). It gives modules one API for agents, tools and structured output, with providers that can be swapped per call:

```php
use UpturnStudio\Ai\Ai;

$response = (new SalesCoach)->prompt('Analyze this sales transcript...', provider: [
    Ai::build(['driver' => 'anthropic', 'key' => $customerKey]),
    Ai::build([
        'driver' => 'anthropic',
        'key' => $fallbackKey,
        'models' => ['text' => ['default' => 'claude-sonnet-5-5']],
    ]),
]);

echo $response;
```

## Status

This is a work in progress. Anthropic is the only driver so far; OpenAI, Gemini and OpenAI-compatible/Ollama are planned next. Streaming, conversation memory, tool approval, queueing, files, images and audio are not built yet.

[`docs/PROGRESS.md`](docs/PROGRESS.md) tracks every section of the Laravel docs against this module, with what is implemented, unit-tested and verified against a live API.

## Requirements

- Magento 2.4.8+ or Mage-OS, PHP 8.2+
- `guzzlehttp/guzzle` ^7.5 (ships with Magento)

## Installation

```bash
composer require upturnstudio/module-ai
php bin/magento module:enable UpturnStudio_Ai
php bin/magento setup:upgrade
```

## Configuration

Go to **Stores > Configuration > UpturnStudio > AI**.

| Setting | Config path | Default |
|---|---|---|
| Default text provider | `upturnstudio_ai/general/default_text_provider` | `anthropic` |
| Anthropic API key (encrypted) | `upturnstudio_ai/anthropic/api_key` | — |
| Anthropic base URL | `upturnstudio_ai/anthropic/url` | `https://api.anthropic.com/v1` |
| Default text model | `upturnstudio_ai/anthropic/text_model` | `claude-opus-5-5` |
| Cheapest text model | `upturnstudio_ai/anthropic/cheapest_text_model` | `claude-haiku-5-5` |
| Smartest text model | `upturnstudio_ai/anthropic/smartest_text_model` | `claude-opus-5-5` |

Set the base URL to route requests through a proxy or gateway.

To check the setup against the real API (this costs tokens):

```bash
php bin/magento upturn:ai:prompt "Say hello" --provider=anthropic
```

`--provider` takes a comma-separated list to fail over in order; `--model` and `--instructions` are optional.

### Extra named providers

Add providers beyond the built-in ones through `di.xml`. The key stays encrypted in config:

```xml
<type name="UpturnStudio\Ai\Model\Config">
    <arguments>
        <argument name="providers" xsi:type="array">
            <item name="claude-eu" xsi:type="array">
                <item name="driver" xsi:type="string">anthropic</item>
                <item name="url" xsi:type="string">https://gateway.example.com/anthropic</item>
                <item name="key_config_path" xsi:type="string">my_module/ai/claude_eu_key</item>
            </item>
        </argument>
    </arguments>
</type>
```

`ai/providers/<name>` in `app/etc/env.php` overrides both, for per-environment settings.

### On-demand providers

`Ai::build()` creates a provider at runtime, for example with a key stored per customer. It accepts `driver` (required), `key`, `url`, `models`, `headers` and `name`, and can be used anywhere a provider is accepted.

## Agents

An agent is a PHP class with instructions. Optional interfaces add history, tools, an output schema and middleware.

```php
namespace Vendor\Module\Ai\Agents;

use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\HasStructuredOutputInterface;
use UpturnStudio\Ai\Api\HasToolsInterface;
use UpturnStudio\Ai\Attributes\MaxSteps;
use UpturnStudio\Ai\Attributes\Provider;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Promptable;
use UpturnStudio\Ai\Schema\JsonSchema;

#[Provider(Lab::Anthropic)]
#[MaxSteps(10)]
class SalesCoach implements AgentInterface, HasToolsInterface, HasStructuredOutputInterface
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach. Analyse transcripts and give feedback.';
    }

    public function tools(): iterable
    {
        return [new RetrievePreviousTranscripts()];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback' => $schema->string()->required(),
            'score' => $schema->integer()->min(1)->max(10)->required(),
        ];
    }
}
```

### Prompting

```php
$response = (new SalesCoach)->prompt('Analyze this transcript...');

$response->text;                 // or (string) $response
$response['score'];              // structured output reads like an array
$response->usage->inputTokens;   // also outputTokens, cache read/write tokens
$response->steps;                // each model round-trip, with its tool calls
$response->raw?->header('request-id');
```

Provider, model and timeout can be overridden per call:

```php
(new SalesCoach)->prompt('...', provider: Lab::Anthropic, model: 'claude-sonnet-5-5', timeout: 120);
```

`SalesCoach::make(...)` creates the agent through the ObjectManager, so constructor dependencies are injected. Agents created with `new` get no interceptors; use the generated `SalesCoachFactory` when plugins on an agent matter.

### Conversation history

Implement `ConversationalInterface::messages()` to supply history, or pass it for one run:

```php
use UpturnStudio\Ai\Messages\Message;

(new SalesCoach)->withMessages([
    new Message('user', 'Here is the transcript...'),
    new Message('assistant', 'The opening was strong.'),
])->prompt('What should they say next time?');
```

### Anonymous agents

```php
use function UpturnStudio\Ai\agent;

$response = agent(instructions: 'You are a Magento expert.')->prompt('What is a plugin?');

$response = agent(
    schema: fn (JsonSchema $schema) => ['number' => $schema->integer()->required()],
)->prompt('Pick a number.');
```

### Attributes

`Provider`, `Model`, `MaxSteps`, `MaxTokens`, `Timeout`, `UseCheapestModel`, `UseSmartestModel`, `CacheInstructions`, `CacheToolDefinitions`, `RepairToolCalls`, `Strict`, `Temperature`, `TopP`, all under `UpturnStudio\Ai\Attributes`. A method of the same meaning on the agent (`provider()`, `model()`, `maxSteps()`, …) takes priority.

Anthropic ignores `Temperature` and `TopP`: its current API version rejects sampling parameters.

## Tools

```php
use UpturnStudio\Ai\Api\ToolInterface;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Tools\ToolRequest;

class LookupOrder implements ToolInterface
{
    public function description(): string
    {
        return 'Look up an order by its increment ID.';
    }

    public function handle(ToolRequest $request): string
    {
        $arguments = $request->validate();   // checks against schema(); errors go back to the model

        return json_encode($this->orders->summary($arguments['increment_id']));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['increment_id' => $schema->string()->required()];
    }
}
```

The tool's name is its `name()` method when it has one, otherwise its class name.

- `->withTools([...])` replaces an agent's tools for one run; pass a closure to extend the declared ones.
- `#[RepairToolCalls]` tells the model which tools exist when it calls one that does not, instead of failing the run.
- A tool that throws fails the run with `ToolExecutionException`.

## Middleware

Middleware runs once per generation step. Return it from `HasMiddlewareInterface::middleware()`.

```php
use UpturnStudio\Ai\Gateway\PendingStep;
use UpturnStudio\Ai\Gateway\StepResponse;
use UpturnStudio\Ai\Middleware\Stop;

public function middleware(): array
{
    return [
        Stop::unless(fn (PendingStep $step) => $step->usage->inputTokens < 50_000),
        function (PendingStep $step, \Closure $next) {
            return $next($step->withoutTools('DeleteOrder'))
                ->then(fn (StepResponse $response) => $this->logger->info($response->text));
        },
    ];
}
```

A step exposes `provider`, `model`, `instructions`, `messages`, `tools`, `steps`, `usage` and `number`, and returns altered copies through `withModel()`, `withInstructions()`, `withMessages()`, `withTools()`, `onlyTools()`, `withoutTools()`, `withMaxTokens()` and `withProviderOptions()`.

## Failover

Pass an array of providers. The next one is tried when a provider is rate limited, overloaded, out of credit or unreachable. An invalid API key or a bad request does not fail over.

```php
(new SalesCoach)->prompt('...', provider: ['anthropic', 'claude-eu']);

// Choose the model per provider:
(new SalesCoach)->prompt('...', provider: ['anthropic' => 'claude-opus-5-5', 'claude-eu' => 'claude-sonnet-5-5']);
```

## Events

Events go through Magento's event manager as `upturn_ai_<snake_case_name>`, with the event object under the `event` key.

| Event | Fired when |
|---|---|
| `upturn_ai_prompting_agent`, `upturn_ai_agent_prompted` | Before and after an agent run |
| `upturn_ai_agent_failed_over`, `upturn_ai_agent_failed` | A provider failed and the run moved on, or failed for good |
| `upturn_ai_starting_step`, `upturn_ai_step_completed`, `upturn_ai_step_failed` | Around each model round-trip |
| `upturn_ai_invoking_tool`, `upturn_ai_tool_invoked`, `upturn_ai_tool_failed` | Around each tool call |

```php
public function execute(Observer $observer): void
{
    /** @var \UpturnStudio\Ai\Events\AgentPrompted $event */
    $event = $observer->getData('event');
    $this->usage->record($event->prompt->agent::class, $event->response->usage);
}
```

## Testing

Fake an agent to return canned responses without calling a provider. Fakes need no provider configuration and no ObjectManager, so they work in plain unit tests.

```php
use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Prompts\AgentPrompt;

protected function tearDown(): void
{
    Ai::reset();
}

public function testCoachIsAsked(): void
{
    SalesCoach::fake([['feedback' => 'Ask more questions', 'score' => 7]]);

    $this->service->review($transcript);

    SalesCoach::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('transcript'));
    SalesCoach::assertPromptedTimes(1);
}
```

- `fake()` takes strings, arrays (structured output) or a closure receiving the prompt. With nothing given it returns a placeholder, or data generated from the schema.
- `fake()->preventStrayPrompts()` throws when a prompt arrives with no response left.
- Assertions: `assertPrompted`, `assertNotPrompted`, `assertPromptedTimes`, `assertNeverPrompted`.

## Using it without static calls

`UpturnStudio\Ai\Ai` is the only class that reaches for the ObjectManager, and only when nothing has been swapped in. For constructor injection, depend on:

- `UpturnStudio\Ai\Api\AiManagerInterface` — `build()`, `provider()`, `textProvider()`
- `UpturnStudio\Ai\Api\AgentRunnerInterface` — `prompt($agent, $prompt, ...)`

## Adding a driver

Implement `Api\Provider\DriverInterface`, returning a provider that implements `Api\Provider\TextProviderInterface` with a gateway implementing `Api\Gateway\TextGatewayInterface`, then register it:

```xml
<type name="UpturnStudio\Ai\Model\AiManager">
    <arguments>
        <argument name="drivers" xsi:type="array">
            <item name="my-driver" xsi:type="object">Vendor\Module\Model\Ai\MyDriver</item>
        </argument>
    </arguments>
</type>
```

A gateway makes one model round-trip; the tool loop lives in `Gateway\TextGenerationLoop`. `Gateway\Anthropic\AnthropicGateway` is the reference implementation.

## Differences from Laravel

- Agents implement `AgentInterface` and friends (`…Interface` suffix) rather than `Agent`, `HasTools`, etc.
- `Ai` is a static locator, not a facade; injectable interfaces are the alternative.
- Collections become plain arrays and readonly objects.
- Tool arguments validate against the tool's own schema; there are no Laravel rule strings.
- Events are Magento events, not Laravel event classes dispatched by type.
- Configuration lives in admin config, `di.xml` and `env.php` instead of `config/ai.php`.

The full list, including what is planned for queueing and streaming, is in [`docs/PROGRESS.md`](docs/PROGRESS.md).

## Development

```bash
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/UpturnStudio/Ai/Test/Unit
php vendor/bin/phpstan analyse -c app/code/UpturnStudio/Ai/phpstan.neon
php vendor/bin/phpcs --standard=app/code/UpturnStudio/Ai/phpcs.xml app/code/UpturnStudio/Ai
```

Tests run against a mocked HTTP layer (`Test/Unit/AiTestCase.php`); none of them call a provider. The paths above are for a module checked out at `app/code/UpturnStudio/Ai`.

## Licence

MIT. See [LICENSE](LICENSE). The architecture follows the [Laravel AI SDK](https://github.com/laravel/ai), which is also MIT-licensed.
