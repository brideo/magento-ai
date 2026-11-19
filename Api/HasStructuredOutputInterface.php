<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Schema\Type;

/**
 * An agent whose response must match a schema.
 */
interface HasStructuredOutputInterface
{
    /**
     * The properties of the response object.
     *
     * @param JsonSchema $schema
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array;
}
