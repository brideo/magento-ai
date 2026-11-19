<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Model\Agent;

use ReflectionClass;

/**
 * Reads agent configuration from PHP attributes, letting a method of the same meaning take priority.
 */
class AttributeReader
{
    /**
     * The value of an attribute on the agent's class or a parent class, or null when absent.
     *
     * @param object $agent
     * @param class-string $attribute
     * @param string|null $method A method on the agent that overrides the attribute when it exists.
     */
    public static function value(object $agent, string $attribute, ?string $method = null): mixed
    {
        if ($method !== null && method_exists($agent, $method)) {
            return $agent->{$method}();
        }

        $instance = self::instance($agent, $attribute);

        return $instance === null ? null : ($instance->value ?? null);
    }

    /**
     * Whether the agent's class, or a parent class, carries an attribute.
     *
     * @param object $agent
     * @param class-string $attribute
     */
    public static function has(object $agent, string $attribute): bool
    {
        return self::instance($agent, $attribute) !== null;
    }

    /**
     * The attribute instance, looked up through the class hierarchy.
     *
     * @param object $agent
     * @param class-string $attribute
     */
    private static function instance(object $agent, string $attribute): ?object
    {
        $class = new ReflectionClass($agent);

        do {
            $attributes = $class->getAttributes($attribute);
            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        } while ($class = $class->getParentClass());

        return null;
    }
}
