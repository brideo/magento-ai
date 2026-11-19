<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses\Data;

/**
 * Which provider and model produced a response.
 */
class Meta
{
    /**
     * @param string|null $provider
     * @param string|null $model
     * @param array<int,mixed> $citations
     */
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly array $citations = []
    ) {
    }
}
