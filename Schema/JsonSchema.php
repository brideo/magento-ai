<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Schema;

use Closure;
use stdClass;

/**
 * The schema factory handed to `schema()` on tools and structured-output agents.
 */
class JsonSchema
{
    /**
     * A string value.
     */
    public function string(): Type
    {
        return new Type('string');
    }

    /**
     * A whole number.
     */
    public function integer(): Type
    {
        return new Type('integer');
    }

    /**
     * Any number.
     */
    public function number(): Type
    {
        return new Type('number');
    }

    /**
     * True or false.
     */
    public function boolean(): Type
    {
        return new Type('boolean');
    }

    /**
     * A list. Chain `->items(...)` to describe its elements.
     */
    public function array(): Type
    {
        return new Type('array');
    }

    /**
     * A nested object, described by an array of properties or a closure returning one.
     *
     * @param Closure|array<string,Type> $properties
     */
    public function object(Closure|array $properties = []): Type
    {
        if ($properties instanceof Closure) {
            $properties = $properties($this);
        }

        return (new Type('object'))->properties($properties);
    }

    /**
     * A value matching any one of several schemas.
     *
     * @param Type[] $types
     */
    public function anyOf(array $types): Type
    {
        return (new Type('anyOf'))->alternatives($types);
    }

    /**
     * Turn the property map returned by a `schema()` method into a complete object schema.
     *
     * @param array<string,Type> $properties
     * @return array<string, mixed>
     */
    public static function fromProperties(array $properties): array
    {
        return ['type' => 'object'] + self::objectKeywords($properties);
    }

    /**
     * The properties, required and additionalProperties keywords of an object.
     *
     * @param array<string,Type> $properties
     * @return array<string, mixed>
     */
    public static function objectKeywords(array $properties): array
    {
        $rendered = [];
        $required = [];

        foreach ($properties as $name => $type) {
            $rendered[$name] = $type->toArray();
            if ($type->isRequired()) {
                $required[] = $name;
            }
        }

        return [
            // An empty PHP array would encode as [], which providers reject for "properties".
            'properties' => $rendered === [] ? new stdClass() : $rendered,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
