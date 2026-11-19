<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Fixtures;

use UpturnStudio\Ai\Api\ToolInterface;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Tools\ToolRequest;

class RandomNumberGenerator implements ToolInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function description(): string
    {
        return 'Generates a number between min and max.';
    }

    public function handle(ToolRequest $request): string
    {
        $arguments = $request->validate();
        $this->calls[] = $arguments;

        return (string) $arguments['max'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'min' => $schema->integer()->min(0)->required(),
            'max' => $schema->integer()->required(),
        ];
    }
}
