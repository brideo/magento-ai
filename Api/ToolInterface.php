<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use Stringable;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Schema\Type;
use UpturnStudio\Ai\Tools\ToolRequest;

/**
 * A tool the model can call. The tool's name is its name() method when it has one, otherwise its class name.
 */
interface ToolInterface
{
    /**
     * What the tool does and when to use it. The model reads this to decide.
     */
    public function description(): Stringable|string;

    /**
     * Run the tool and return its result for the model.
     *
     * @param ToolRequest $request
     */
    public function handle(ToolRequest $request): Stringable|string;

    /**
     * The tool's arguments.
     *
     * @param JsonSchema $schema
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array;
}
