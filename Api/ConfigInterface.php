<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

/**
 * Where named provider configuration comes from.
 */
interface ConfigInterface
{
    /**
     * The configuration of a named provider, shaped like an Ai::build() array, or null when it is not configured.
     *
     * @param string $name
     * @return array<string, mixed>|null
     */
    public function providerConfig(string $name): ?array;

    /**
     * The provider used when none is given, for a capability: text, image, audio or transcription.
     *
     * @param string $capability
     */
    public function defaultProvider(string $capability = 'text'): string;
}
