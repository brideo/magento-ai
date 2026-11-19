<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

use UpturnStudio\Ai\Http\Response as HttpResponse;

/**
 * One model round-trip inside an agent run, with the tool results it led to.
 */
class Step
{
    /**
     * @param string $text
     * @param ToolCall[] $toolCalls
     * @param ToolResult[] $toolResults
     * @param FinishReason $finishReason
     * @param TextUsage $usage
     * @param Meta $meta
     * @param string $reasoning
     * @param HttpResponse|null $raw
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls,
        public readonly array $toolResults,
        public readonly FinishReason $finishReason,
        public readonly TextUsage $usage,
        public readonly Meta $meta,
        public readonly string $reasoning = '',
        public readonly ?HttpResponse $raw = null
    ) {
    }
}
