<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use Throwable;
use UpturnStudio\Ai\Responses\Data\ToolCall;

/**
 * A tool threw while running.
 */
class ToolFailed
{
    /**
     * @param string $invocationId
     * @param object $tool
     * @param ToolCall $toolCall
     * @param Throwable $exception
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly object $tool,
        public readonly ToolCall $toolCall,
        public readonly Throwable $exception
    ) {
    }
}
