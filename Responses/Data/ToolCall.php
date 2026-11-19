<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

/**
 * A tool call the model asked for.
 */
class ToolCall
{
    /**
     * @param string $id
     * @param string $name
     * @param array<string,mixed> $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments = []
    ) {
    }
}
