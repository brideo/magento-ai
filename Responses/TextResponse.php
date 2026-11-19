<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses;

use UpturnStudio\Ai\Http\Response as HttpResponse;
use UpturnStudio\Ai\Messages\Message;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Responses\Data\Meta;
use UpturnStudio\Ai\Responses\Data\Step;
use UpturnStudio\Ai\Responses\Data\TextUsage;
use UpturnStudio\Ai\Responses\Data\ToolCall;
use UpturnStudio\Ai\Responses\Data\ToolResult;

/**
 * The outcome of a text generation run: the final text plus everything that happened on the way.
 */
class TextResponse
{
    /**
     * @param string $text The text of the final step.
     * @param TextUsage $usage Usage summed over every step.
     * @param Meta $meta
     * @param Message[] $messages The assistant and tool-result messages the run produced.
     * @param ToolCall[] $toolCalls
     * @param ToolResult[] $toolResults
     * @param Step[] $steps
     * @param string $reasoning
     * @param array<string,mixed>|null $structured The decoded output when a schema was requested.
     * @param FinishReason $finishReason
     * @param HttpResponse|null $raw The final step's HTTP response. Null on fakes and when streaming.
     */
    public function __construct(
        public readonly string $text,
        public readonly TextUsage $usage,
        public readonly Meta $meta,
        public readonly array $messages = [],
        public readonly array $toolCalls = [],
        public readonly array $toolResults = [],
        public readonly array $steps = [],
        public readonly string $reasoning = '',
        public readonly ?array $structured = null,
        public readonly FinishReason $finishReason = FinishReason::Stop,
        public readonly ?HttpResponse $raw = null
    ) {
    }

    /**
     * The response text.
     */
    public function __toString(): string
    {
        return $this->text;
    }
}
