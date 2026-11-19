<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Messages;

use UpturnStudio\Ai\Responses\Data\ToolResult;

/**
 * The results of the tool calls in the preceding assistant message.
 */
class ToolResultMessage extends Message
{
    /**
     * @param ToolResult[] $toolResults
     */
    public function __construct(
        public readonly array $toolResults
    ) {
        parent::__construct(MessageRole::ToolResult, '');
    }
}
