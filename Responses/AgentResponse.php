<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses;

use Closure;

/**
 * What an agent returned for a prompt. Cast it to a string for the text.
 */
class AgentResponse extends TextResponse
{
    /** @var string|null */
    public ?string $conversationId = null;

    /** @var string|null */
    public ?string $userMessageId = null;

    /** @var string|null */
    public ?string $assistantMessageId = null;

    /**
     * @param string $invocationId A unique id for this run, shared by its events.
     * @param TextResponse $response
     */
    public function __construct(
        public readonly string $invocationId,
        TextResponse $response
    ) {
        parent::__construct(
            $response->text,
            $response->usage,
            $response->meta,
            $response->messages,
            $response->toolCalls,
            $response->toolResults,
            $response->steps,
            $response->reasoning,
            $response->structured,
            $response->finishReason,
            $response->raw
        );
    }

    /**
     * Pass the response to a callback and return it, so prompt() and queue() read alike.
     *
     * @param Closure $callback
     */
    public function then(Closure $callback): static
    {
        $callback($this);

        return $this;
    }
}
