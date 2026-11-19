<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

/**
 * Why the model stopped generating.
 */
enum FinishReason: string
{
    case Stop = 'stop';
    case ToolCalls = 'tool_calls';
    case Length = 'length';
    case ContentFilter = 'content_filter';
    case Refusal = 'refusal';
    // The provider paused a long server-side turn; the request must be sent again to continue it.
    case Continue = 'continue';
    case Cancelled = 'cancelled';
    case Error = 'error';
    case Unknown = 'unknown';
}
