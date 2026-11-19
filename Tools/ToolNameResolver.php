<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Tools;

/**
 * Works out the name a tool is offered to the model under.
 */
class ToolNameResolver
{
    /**
     * The tool's name() when it has one, otherwise its class name without the namespace.
     *
     * @param object $tool
     */
    public static function resolve(object $tool): string
    {
        if (method_exists($tool, 'name')) {
            return (string) $tool->name();
        }

        $class = $tool::class;
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
