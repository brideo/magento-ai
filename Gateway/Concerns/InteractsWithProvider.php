<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\Concerns;

use UpturnStudio\Ai\Api\Provider\ProviderInterface;
use UpturnStudio\Ai\Api\ToolInterface;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Tools\ToolNameResolver;

/**
 * Small helpers every HTTP gateway needs: the base URL, configured headers, tool definitions and
 * structured-output decoding.
 */
trait InteractsWithProvider
{
    /**
     * The provider's base URL without a trailing slash, or the default when none is configured.
     *
     * @param ProviderInterface $provider
     * @param string|null $default
     */
    protected function endpoint(ProviderInterface $provider, ?string $default): ?string
    {
        $url = $provider->additionalConfiguration()['url'] ?? null;
        $url = is_string($url) && $url !== '' ? $url : $default;

        return $url === null ? null : rtrim($url, '/');
    }

    /**
     * Headers configured on the provider, sent with every request.
     *
     * @param ProviderInterface $provider
     * @return array<string,string>
     */
    protected function configuredHeaders(ProviderInterface $provider): array
    {
        return (array) ($provider->additionalConfiguration()['headers'] ?? []);
    }

    /**
     * The function tools among a tool list, as name, description and argument schema.
     *
     * @param array<int,object> $tools
     * @return array<int,array{name:string,description:string,schema:array}>
     */
    protected function functionTools(array $tools): array
    {
        $definitions = [];

        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $definitions[] = [
                    'name' => ToolNameResolver::resolve($tool),
                    'description' => (string) $tool->description(),
                    'schema' => JsonSchema::fromProperties($tool->schema(new JsonSchema())),
                ];
            }
        }

        return $definitions;
    }

    /**
     * Decode the model's text as structured output when a schema was requested.
     *
     * @param array<string,mixed>|null $schema
     * @param string $text
     * @return array<string,mixed>|null
     */
    protected function decodeStructured(?array $schema, string $text): ?array
    {
        if ($schema === null || $text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Drop null values from an array.
     *
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    protected function withoutNulls(array $values): array
    {
        return array_filter($values, static fn ($value) => $value !== null);
    }
}
