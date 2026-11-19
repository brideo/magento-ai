<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use UpturnStudio\Ai\Responses\Data\ToolCall;
use UpturnStudio\Ai\Responses\Data\ToolResult;

/**
 * A tool ran and returned a result.
 */
class ToolInvoked
{
    /**
     * @param string $invocationId
     * @param object $tool
     * @param ToolCall $toolCall
     * @param ToolResult $result
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly object $tool,
        public readonly ToolCall $toolCall,
        public readonly ToolResult $result
    ) {
    }
}
