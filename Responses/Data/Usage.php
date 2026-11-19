<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

/**
 * Token usage for one operation.
 */
class Usage
{
    /**
     * @param int $inputTokens
     * @param int $outputTokens
     */
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0
    ) {
    }

    /**
     * Input plus output tokens.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
