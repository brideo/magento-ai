<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Provider;

/**
 * A local Ollama server, reached through its OpenAI-compatible endpoint.
 */
class OllamaProvider extends OpenAiCompatibleProvider
{
    public const DEFAULT_URL = 'http://localhost:11434/v1';

    /**
     * @inheritDoc
     */
    protected function fallbackTextModel(): string
    {
        return 'qwen3.5:4b';
    }

    /**
     * @inheritDoc
     */
    protected function defaultUrl(): ?string
    {
        return self::DEFAULT_URL;
    }
}
