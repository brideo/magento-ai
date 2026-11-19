<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Messages;

use UpturnStudio\Ai\Responses\Data\ToolCall;

/**
 * A message from the model: its text plus any tool calls it asked for.
 */
class AssistantMessage extends Message
{
    /**
     * @param string $content
     * @param ToolCall[] $toolCalls
     * @param array<int,array<string,mixed>> $replayBlocks Provider content blocks that must be sent back verbatim
     *                                                        on the next step (thinking blocks, server tool use).
     */
    public function __construct(
        string $content = '',
        public readonly array $toolCalls = [],
        public readonly array $replayBlocks = []
    ) {
        parent::__construct(MessageRole::Assistant, $content);
    }
}
