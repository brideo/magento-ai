<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai;

use Closure;
use Stringable;
use UpturnStudio\Ai\Api\HasStructuredOutputInterface;
use UpturnStudio\Ai\Schema\JsonSchema;

/**
 * An inline agent with an output schema. Create one with agent(schema: fn (JsonSchema $schema) => [...]).
 */
class StructuredAnonymousAgent extends AnonymousAgent implements HasStructuredOutputInterface
{
    /**
     * @param Stringable|string $instructions
     * @param iterable $messages
     * @param iterable $tools
     * @param Closure|null $schema
     */
    public function __construct(
        Stringable|string $instructions = '',
        iterable $messages = [],
        iterable $tools = [],
        private readonly ?Closure $schema = null
    ) {
        parent::__construct($instructions, $messages, $tools);
    }

    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schema === null ? [] : ($this->schema)($schema);
    }
}
