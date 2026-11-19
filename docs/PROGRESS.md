# UpturnStudio_Ai — progress against the Laravel AI SDK docs

This file tracks the port of the [Laravel AI SDK](https://laravel.com/docs/ai-sdk) to Magento, one row per docs heading, in document order.

- **Upstream pinned:** `laravel/ai` branch `1.x` at commit `a6d563130fd3add59d34631b8bf0a2f5b9b43048` (read 2026-11-20). Docs: 13.x `ai-sdk.md`.
- **Drivers in v1:** Anthropic, OpenAI, Gemini, OpenAI-compatible (Ollama is a preset of it).
- **Status values:** `todo`, `wip`, `done`, `deferred` (tracked, not planned for v1), `n/a` (no Magento equivalent).
- **Columns:** *Impl* = code written. *Unit* = covered by `Test/Unit`. *Live* = exercised against a real provider API with `bin/magento upturn:ai:prompt`.

Update the row in the same change that moves a section forward.

## Phases

| # | Phase | Status |
|---|---|---|
| 0 | Scaffold and tracking | done |
| 1 | Core + streaming spike | wip — core done; SSE parser done; `EventStream` response spike still to do |
| 2 | Agent loop + Anthropic gateway | done — plain text prompt live-verified 2026-11-20; tools and structured output not yet run live |
| 3 | Remaining text gateways (OpenAI, Gemini, OpenAI-compatible/Ollama) | todo |
| 4 | Streaming (SSE response, Vercel and AG-UI protocols) | todo |
| 5 | Conversation memory and human tool approval | todo |
| 6 | Queueing | todo |
| 7 | Provider tools and files | todo |
| 8 | Images, audio, transcription | todo |
| 9 | Sub-agents, skills, MCP adapter, filesystem tools, CLI generators | todo |
| 10 | README and final pass | todo |

## How to check

```bash
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/UpturnStudio/Ai/Test/Unit
php vendor/bin/phpstan analyse -c app/code/UpturnStudio/Ai/phpstan.neon
php vendor/bin/phpcs --standard=app/code/UpturnStudio/Ai/phpcs.xml app/code/UpturnStudio/Ai
php bin/magento upturn:ai:prompt "Say hello" --provider=anthropic   # live: needs an API key, costs tokens
```

## Sections

| Docs section | Magento equivalent | Phase | Impl | Unit | Live | Notes |
|---|---|---|---|---|---|---|
| Introduction | README | 10 | todo | n/a | n/a | |
| Installation | `module:enable UpturnStudio_Ai`, `setup:upgrade` | 0 | done | n/a | done |  |
| Installation › Configuration | `etc/config.xml`, admin section `upturnstudio_ai`, `Model\Config` | 1 | wip | done | done | Anthropic group done; admin section is `upturnstudio_ai` (house naming). Keys encrypted with `Backend\Encrypted` |
| Installation › Custom Base URLs | `url` per provider in config | 1 | done | done | todo | Anthropic |
| Installation › OpenAI-Compatible Providers | `openai-compatible` driver, extra named providers via di.xml `providers` | 3 | todo | todo | todo | |
| … › OpenAI-Compatible Embeddings | — | – | deferred | deferred | deferred | |
| … › OpenAI-Compatible Transcriptions | `Transcription` on the compatible driver | 8 | todo | todo | todo | |
| … › OpenAI-Compatible Classification | — | – | deferred | deferred | deferred | |
| Installation › On-Demand Providers | `Ai::build([...])`, `AiManagerInterface::build()` | 1 | done | done | todo |  |
| Installation › Provider Support | Capability matrix below | 3 | todo | n/a | todo | |
| Agents | `Api\AgentInterface` + `Promptable`; `upturn:ai:make:agent` | 2, 9 | wip | done | todo | `make:agent` generator pending (phase 9) |
| Agents › Prompting | `prompt()`, `::make()`, per-call provider/model/timeout | 2 | done | done | done |  |
| … › Raw HTTP Responses | `$response->raw` (`Http\Response`), per step | 3 | done | done | todo | Anthropic |
| Agents › Conversation Context | `ConversationalInterface::messages()`, `withMessages()` | 2 | done | done | todo |  |
| … › Remembering Conversations | `RemembersConversations`, `upturnstudio_ai_conversation*` tables | 5 | todo | todo | todo | |
| … › Conversation Participants | `(type, id)` pairs, e.g. `customer`, `admin_user` | 5 | todo | todo | todo | |
| … › Inspecting Stored Conversations | `ConversationStoreInterface` + pagination/ownership/approval contracts | 5 | todo | todo | todo | |
| Agents › Structured Output | `HasStructuredOutputInterface`, `Schema\JsonSchema` | 2 | done | done | todo |  |
| … › Nested Objects | `$schema->object(...)` | 2 | done | done | todo |  |
| … › Arrays of Objects | `$schema->array()->items(...)`, `anyOf()` | 2 | done | done | todo |  |
| Agents › Attachments | `Files\Document`, `Files\Image` (`fromPath`, `fromMedia`, `fromUrl`, `fromString`) | 3 | todo | todo | todo | `fromStorage` becomes `fromMedia` |
| Agents › Streaming | `stream()`, `StreamableAgentResponse`, `Response\EventStream` | 4 | todo | todo | todo | |
| … › Stream Protocols | `Streaming\Protocol\{Native,VercelData,AgentUserInteraction}` | 4 | todo | todo | todo | |
| … › Frontend Integration | `Vercel::chat()`, `AgentUserInteraction::chat()` over `RequestInterface` | 4 | todo | todo | todo | |
| Agents › Broadcasting | — | – | n/a | n/a | n/a | No broadcasting layer in Magento |
| … › Skipping Oversized Events | — | – | n/a | n/a | n/a | |
| Agents › Queueing | `queue()` on MessageQueue topic `upturnstudio_ai.agent.invoke` | 6 | todo | todo | todo | Callbacks become events / handler class |
| Agents › Tools | `Api\ToolInterface`; `upturn:ai:make:tool` | 2, 9 | wip | done | todo | `make:tool` generator pending (phase 9) |
| … › Runtime Tool Overrides | `withTools()` | 2 | done | done | n/a |  |
| … › Validating Tool Arguments | `ToolRequest::validate()` against the tool schema | 2 | done | done | n/a | No Laravel rule strings |
| … › Repairing Tool Calls | `#[RepairToolCalls]` | 2 | done | done | n/a |  |
| … › Similarity Search | — | – | deferred | deferred | deferred | Needs embeddings |
| Agents › Deferred Tool Loading | `Providers\Tools\ToolSearch` | 7 | todo | todo | todo | |
| Agents › File Storage Tools | `Tools\FileStorage` over `Magento\Framework\Filesystem` | 9 | todo | todo | n/a | |
| Agents › MCP Tools | `Tools\McpToolAdapter` over `UpturnStudio\Mcp\Api\ToolInterface` | 9 | todo | todo | n/a | Remote MCP client deferred |
| Agents › Skills | `HasSkillsInterface`, `SKILL.md`, `LoadSkill` | 9 | todo | todo | n/a | |
| Agents › Provider Tools › Web Search | `Providers\Tools\WebSearch` | 7 | todo | todo | todo | |
| … › Web Fetch | `Providers\Tools\WebFetch` | 7 | todo | todo | todo | |
| … › File Search | — | – | deferred | deferred | deferred | Needs vector stores |
| … › Code Execution | `Providers\Tools\CodeExecution` | 7 | todo | todo | todo | |
| Agents › Sub-Agents | `Tools\AgentTool`, `CanActAsToolInterface` | 9 | todo | todo | todo | |
| Agents › Middleware | `HasMiddlewareInterface`, `PendingStep`; `upturn:ai:make:middleware` | 2, 9 | wip | done | n/a | Generator pending (phase 9) |
| … › Stopping Runs | `Middleware\Stop::when()/unless()`, `StepResult::stop()` | 2 | done | done | n/a |  |
| Agents › Anonymous Agents | `UpturnStudio\Ai\agent()` | 2 | done | done | done |  |
| Agents › Agent Configuration | `Attributes\*` | 2 | done | wip | todo | Anthropic ignores Temperature/TopP: API version 2026-10-21 rejects them |
| Agents › Provider Options | `HasProviderOptionsInterface` | 3 | wip | todo | todo | Anthropic merges them into the request body |
| … › Custom HTTP Headers | `headers` in provider config, `withHeaders()` | 3 | wip | done | todo | Provider `headers` config done; per-request `withHeaders()` pending |
| Agents › Prompt Caching | `#[CacheInstructions]`, `#[CacheToolDefinitions]` | 3 | wip | todo | todo | Anthropic breakpoints implemented |
| Human Tool Approval | `ApprovableInterface`, `Approvals\{Approval,Decision,Decisions}` | 5 | todo | todo | todo | |
| Human Tool Approval › Complete Approval Flow | Example controller in README | 5 | todo | n/a | todo | |
| Images | `Image::of()` | 8 | todo | todo | todo | `store()` writes to media |
| Audio (TTS) | `Audio::of()` | 8 | todo | todo | todo | |
| Transcriptions (STT) | `Transcription::fromPath()` | 8 | todo | todo | todo | |
| Text Summarization | `Model\Summarizer` service | 9 | todo | todo | todo | No string macros |
| Embeddings | — | – | deferred | deferred | deferred | |
| Embeddings › Multimodal Embeddings | — | – | deferred | deferred | deferred | |
| Embeddings › Querying Embeddings | — | – | deferred | deferred | deferred | |
| Embeddings › Caching Embeddings | — | – | deferred | deferred | deferred | |
| Reranking | — | – | deferred | deferred | deferred | |
| Reranking › Reranking Collections | — | – | deferred | deferred | deferred | |
| Classification | — | – | deferred | deferred | deferred | |
| Classification › Classifying Images | — | – | deferred | deferred | deferred | |
| Classification › Yes or No Decisions | — | – | deferred | deferred | deferred | |
| Classification › Choosing From Collections | — | – | deferred | deferred | deferred | |
| Files | `Files\Document::fromPath()->put()`, `fromId()->get()/delete()` | 7 | todo | todo | todo | |
| Files › Using Stored Files in Conversations | `Document::fromId()` as attachment | 7 | todo | todo | todo | |
| Vector Stores | — | – | deferred | deferred | deferred | |
| Vector Stores › Adding Files to Stores | — | – | deferred | deferred | deferred | |
| Usage | `Responses\Data\{Usage,TextUsage,ImageUsage,TranscriptionUsage}` | 1, 8 | wip | done | todo | `TextUsage` done; image and transcription usage in phase 8 |
| Failover | Provider arrays; `FailoverableException` | 2, 8 | wip | done | todo | Agents done; media in phase 8 |
| Testing › Agents | `Agent::fake()`, `assertPrompted*`, `preventStrayPrompts()` | 2 | wip | done | n/a | Queue assertions arrive with phase 6 |
| Testing › Images | `Image::fake()` + assertions | 8 | todo | todo | n/a | |
| Testing › Audio | `Audio::fake()` + assertions | 8 | todo | todo | n/a | |
| Testing › Transcriptions | `Transcription::fake()` + assertions | 8 | todo | todo | n/a | |
| Testing › Embeddings | — | – | deferred | deferred | n/a | |
| Testing › Reranking | — | – | deferred | deferred | n/a | |
| Testing › Classification | — | – | deferred | deferred | n/a | |
| Testing › Files | `Files::fake()` + assertions | 7 | todo | todo | n/a | |
| Testing › Vector Stores | — | – | deferred | deferred | n/a | |
| Events | Magento events `upturn_ai_<snake_name>`, DTO under the `event` key | 2–8 | todo | todo | n/a | Checklist below |

## Provider capability matrix

Fill in as each driver lands: `done`, `todo`, or `–` where the provider does not offer it.

| Capability | Anthropic | OpenAI | Gemini | OpenAI-compatible / Ollama |
|---|---|---|---|---|
| Text | done | todo | todo | todo |
| Streaming | todo | todo | todo | todo |
| Tools | done | todo | todo | todo |
| Structured output | done | todo | todo | todo |
| Attachments | todo | todo | todo | todo |
| Images | – | todo | todo | – |
| TTS | – | todo | todo | – |
| STT | – | todo | todo | todo |
| Files | todo | todo | todo | – |
| Web search | todo | todo | todo | – |
| Web fetch | todo | – | todo | – |
| Code execution | todo | todo | todo | – |
| Prompt caching attributes | done | – (automatic) | – (automatic) | – |

## Events checklist

Magento event name is `upturn_ai_` plus the snake-cased class name, e.g. `upturn_ai_agent_prompted`.

| Group | Events | Status |
|---|---|---|
| Agent | `PromptingAgent`, `AgentPrompted`, `AgentFailed`, `AgentFailedOver` | done |
| Agent streaming | `StreamingAgent`, `AgentStreamed` | todo |
| Steps | `StartingStep`, `StepCompleted`, `StepFailed` | done |
| Tools | `InvokingTool`, `ToolInvoked`, `ToolFailed` | done |
| Approvals | `ToolApprovalRequested`, `ToolApprovalResolved` | todo |
| Failover (non-agent) | `ProviderFailedOver` | todo |
| Images | `GeneratingImage`, `ImageGenerated`, `ImageFailed` | todo |
| Audio | `GeneratingAudio`, `AudioGenerated`, `AudioFailed` | todo |
| Transcription | `GeneratingTranscription`, `TranscriptionGenerated`, `TranscriptionFailed` | todo |
| Files | `StoringFile`, `FileStored`, `StoringFileFailed`, `FileDeleted` | todo |
| Embeddings | `GeneratingEmbeddings`, `EmbeddingsGenerated`, `EmbeddingsFailed` | deferred |
| Reranking | `Reranking`, `Reranked`, `RerankingFailed` | deferred |
| Classification | `Classifying`, `Classified`, `ClassificationFailed` | deferred |
| Vector stores | `CreatingStore`, `StoreCreated`, `CreatingStoreFailed`, `StoreDeleted`, `AddingFileToStore`, `FileAddedToStore`, `AddingFileToStoreFailed`, `RemovingFileFromStore`, `FileRemovedFromStore`, `RemovingFileFromStoreFailed` | deferred |

## Deviations from Laravel

- **Static access:** `UpturnStudio\Ai\Ai` is the single static locator. It falls back to the ObjectManager only when nothing has been swapped in, and is the only class that does so. Inject `Api\AiManagerInterface` / `Api\AgentRunnerInterface` for strict DI.
- **Pluggable agents:** agents created with `new` get no interceptors. Use the generated `*Factory` when plugins on an agent matter.
- **Queued callbacks:** `queue()->then()/catch()` become the events `upturn_ai_queued_agent_completed|failed` plus an optional handler class. Queueable agents implement `QueueableAgentInterface`. On-demand providers with inline keys cannot be queued.
- **Broadcasting:** not applicable.
- **Macros:** `Str`/`Collection` macros become injectable services.
- **Tool argument validation:** against the tool's own JSON schema, not Laravel rule strings.
- **Collections:** plain arrays and readonly DTOs.
- **Ollama:** the OpenAI-compatible driver with a default base URL.
- **Storage:** `fromStorage()` / `store()` use the Magento media directory.

## Port map

Record each ported upstream class here as it lands (`laravel/ai` path → module path).

| `laravel/ai` (`src/`) | `UpturnStudio\Ai` | Status |
|---|---|---|
| `Ai.php`, `AiManager.php` | `Ai.php`, `Model/AiManager.php` | done |
| `Promptable.php` | `Promptable.php`, `Model/Agent/AgentRunner.php` | done |
| `Gateway/TextGenerationLoop.php` | `Gateway/TextGenerationLoop.php` | done |
| `Gateway/Concerns/HandlesFailoverErrors.php` | `Gateway/Concerns/HandlesFailoverErrors.php` | done |
| `Gateway/FakeTextGateway.php` | `Gateway/FakeTextGateway.php` | done |
| `Gateway/Anthropic/*` | `Gateway/Anthropic/*` | wip (text only; streaming, files and provider tools pending) |
| `Gateway/OpenAi/*` | `Gateway/OpenAi/*` | todo |
| `Gateway/Gemini/*` | `Gateway/Gemini/*` | todo |
| `Gateway/OpenAiCompatible/*` | `Gateway/OpenAiCompatible/*` | todo |
| `illuminate/json-schema` | `Schema/JsonSchema.php`, `Schema/Type.php` | done |
