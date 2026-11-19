<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use UpturnStudio\Ai\Responses\Data\ToolCall;

/**
 * A tool is about to run.
 */
class InvokingTool
{
    /**
     * @param string $invocationId
     * @param object $tool
     * @param ToolCall $toolCall
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly object $tool,
        public readonly ToolCall $toolCall
    ) {
    }
}
