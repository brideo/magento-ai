<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

/**
 * Token usage for text generation. The cache and reasoning counts are null when the provider does not report them.
 *
 * `inputTokens` is the total input, including tokens read from or written to the cache.
 */
class TextUsage extends Usage
{
    /**
     * @param int $inputTokens
     * @param int $outputTokens
     * @param int|null $cacheReadInputTokens
     * @param int|null $cacheWriteInputTokens
     * @param int|null $reasoningTokens
     */
    public function __construct(
        int $inputTokens = 0,
        int $outputTokens = 0,
        public readonly ?int $cacheReadInputTokens = null,
        public readonly ?int $cacheWriteInputTokens = null,
        public readonly ?int $reasoningTokens = null
    ) {
        parent::__construct($inputTokens, $outputTokens);
    }

    /**
     * Input tokens that were neither read from nor written to the cache. Price these at the normal input rate.
     */
    public function uncachedInputTokens(): int
    {
        return max(0, $this->inputTokens - (int) $this->cacheReadInputTokens - (int) $this->cacheWriteInputTokens);
    }

    /**
     * The sum of this usage and another, as a new object.
     *
     * @param TextUsage $other
     */
    public function add(TextUsage $other): TextUsage
    {
        return new TextUsage(
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
            self::sum($this->cacheReadInputTokens, $other->cacheReadInputTokens),
            self::sum($this->cacheWriteInputTokens, $other->cacheWriteInputTokens),
            self::sum($this->reasoningTokens, $other->reasoningTokens)
        );
    }

    /**
     * Add two optional counts, staying null only when neither was reported.
     *
     * @param int|null $a
     * @param int|null $b
     */
    private static function sum(?int $a, ?int $b): ?int
    {
        return $a === null && $b === null ? null : (int) $a + (int) $b;
    }
}
