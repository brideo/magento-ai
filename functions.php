<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai;

use Closure;
use Stringable;

if (!function_exists(__NAMESPACE__ . '\agent')) {
    /**
     * Create an agent without writing a class.
     *
     * @param Stringable|string $instructions
     * @param iterable $messages
     * @param iterable $tools
     * @param Closure|null $schema Receives a JsonSchema and returns the output properties.
     */
    function agent(
        Stringable|string $instructions = '',
        iterable $messages = [],
        iterable $tools = [],
        ?Closure $schema = null
    ): AnonymousAgent {
        return $schema === null
            ? new AnonymousAgent($instructions, $messages, $tools)
            : new StructuredAnonymousAgent($instructions, $messages, $tools, $schema);
    }
}
