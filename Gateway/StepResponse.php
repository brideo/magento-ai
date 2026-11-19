<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway;

use UpturnStudio\Ai\Http\Response as HttpResponse;
use UpturnStudio\Ai\Responses\Data\FinishReason;
use UpturnStudio\Ai\Responses\Data\Meta;
use UpturnStudio\Ai\Responses\Data\TextUsage;
use UpturnStudio\Ai\Responses\Data\ToolCall;

/**
 * What a gateway returns for one model round-trip.
 */
class StepResponse
{
    /**
     * @param string $text
     * @param ToolCall[] $toolCalls
     * @param FinishReason $finishReason
     * @param TextUsage|null $usage
     * @param Meta|null $meta
     * @param array<string,mixed>|null $structured
     * @param string $reasoning
     * @param array<int,array<string,mixed>> $replayBlocks Provider content to send back verbatim next step.
     * @param HttpResponse|null $raw
     */
    public function __construct(
        public readonly string $text = '',
        public readonly array $toolCalls = [],
        public readonly FinishReason $finishReason = FinishReason::Stop,
        public ?TextUsage $usage = null,
        public ?Meta $meta = null,
        public readonly ?array $structured = null,
        public readonly string $reasoning = '',
        public readonly array $replayBlocks = [],
        public readonly ?HttpResponse $raw = null
    ) {
        $this->usage ??= new TextUsage();
        $this->meta ??= new Meta();
    }
}
