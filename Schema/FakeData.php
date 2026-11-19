<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Schema;

/**
 * Generates placeholder data matching a schema, for faked structured agents with no response given.
 */
class FakeData
{
    /**
     * A value that satisfies the schema.
     *
     * @param array<string,mixed> $schema
     */
    public static function for(array $schema): mixed
    {
        if (isset($schema['enum'][0])) {
            return $schema['enum'][0];
        }

        if (isset($schema['anyOf'][0])) {
            return self::for($schema['anyOf'][0]);
        }

        $type = array_values(array_diff((array) ($schema['type'] ?? 'string'), ['null']))[0] ?? 'string';

        return match ($type) {
            'integer' => (int) ($schema['minimum'] ?? 1),
            'number' => (float) ($schema['minimum'] ?? 1.0),
            'boolean' => true,
            'array' => isset($schema['items']) ? [self::for($schema['items'])] : [],
            'object' => array_map(
                static fn (array $property) => self::for($property),
                (array) ($schema['properties'] ?? [])
            ),
            default => 'fake',
        };
    }
}
