<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

/**
 * What a tool returned for one call.
 */
class ToolResult
{
    /**
     * @param string $id The id of the tool call this answers.
     * @param string $name
     * @param array<string,mixed> $arguments
     * @param string $result
     * @param bool $isError
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
        public readonly string $result,
        public readonly bool $isError = false
    ) {
    }
}
