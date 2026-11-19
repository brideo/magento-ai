<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Messages;

/**
 * Who said a message.
 */
enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case ToolResult = 'tool_result';
}
