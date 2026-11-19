<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\Anthropic;

/**
 * Removes the JSON schema keywords Anthropic's structured outputs reject, keeping them as hints in the
 * description so the model still sees the constraint.
 */
class AnthropicSchemaSanitizer
{
    private const UNSUPPORTED = [
        'minimum' => 'minimum',
        'maximum' => 'maximum',
        'multipleOf' => 'multiple of',
        'minLength' => 'minimum length',
        'maxLength' => 'maximum length',
        'minItems' => 'minimum items',
        'maxItems' => 'maximum items',
    ];

    /**
     * A copy of the schema that Anthropic accepts.
     *
     * @param array<string,mixed> $schema
     * @return array<string, mixed>
     */
    public function sanitize(array $schema): array
    {
        $hints = [];
        foreach (self::UNSUPPORTED as $keyword => $label) {
            if (array_key_exists($keyword, $schema)) {
                $hints[] = $label . ' ' . $schema[$keyword];
                unset($schema[$keyword]);
            }
        }

        if ($hints !== []) {
            $schema['description'] = trim(
                ($schema['description'] ?? '') . ' (' . implode(', ', $hints) . ')'
            );
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                $schema['properties'][$name] = $this->sanitize((array) $property);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->sanitize($schema['items']);
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            $schema['anyOf'] = array_map(fn (array $alternative) => $this->sanitize($alternative), $schema['anyOf']);
        }

        if (in_array('object', (array) ($schema['type'] ?? []), true)) {
            $schema['additionalProperties'] = false;
        }

        return $schema;
    }
}
