<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Schema;

use PHPUnit\Framework\TestCase;
use UpturnStudio\Ai\Schema\JsonSchema;
use UpturnStudio\Ai\Schema\Validator;

/**
 * @covers \UpturnStudio\Ai\Schema\JsonSchema
 * @covers \UpturnStudio\Ai\Schema\Type
 * @covers \UpturnStudio\Ai\Schema\Validator
 */
class JsonSchemaTest extends TestCase
{
    public function testBuildsAnObjectSchemaWithRequiredProperties(): void
    {
        $schema = new JsonSchema();

        $result = JsonSchema::fromProperties([
            'feedback' => $schema->string()->description('What to improve')->required(),
            'score' => $schema->integer()->min(1)->max(10)->required(),
            'note' => $schema->string()->nullable(),
        ]);

        $this->assertSame('object', $result['type']);
        $this->assertSame(['feedback', 'score'], $result['required']);
        $this->assertFalse($result['additionalProperties']);
        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 10], $result['properties']['score']);
        $this->assertSame(['string', 'null'], $result['properties']['note']['type']);
        $this->assertSame('What to improve', $result['properties']['feedback']['description']);
    }

    public function testBuildsNestedObjectsArraysAndAnyOf(): void
    {
        $schema = new JsonSchema();

        $result = JsonSchema::fromProperties([
            'metadata' => $schema->object(fn (JsonSchema $schema) => [
                'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            ])->required(),
            'items' => $schema->array()->items($schema->object(['name' => $schema->string()->required()]))->max(3),
            'content' => $schema->anyOf([$schema->string(), $schema->integer()]),
        ]);

        $this->assertSame(['low', 'medium', 'high'], $result['properties']['metadata']['properties']['confidence']['enum']);
        $this->assertSame(['confidence'], $result['properties']['metadata']['required']);
        $this->assertSame(3, $result['properties']['items']['maxItems']);
        $this->assertSame('object', $result['properties']['items']['items']['type']);
        $this->assertCount(2, $result['properties']['content']['anyOf']);
    }

    public function testAnEmptyObjectEncodesPropertiesAsAJsonObject(): void
    {
        $this->assertStringContainsString('"properties":{}', (string) json_encode(JsonSchema::fromProperties([])));
    }

    public function testValidatorReportsMissingWrongTypeAndOutOfRangeValues(): void
    {
        $schema = new JsonSchema();
        $definition = JsonSchema::fromProperties([
            'city' => $schema->string()->required(),
            'days' => $schema->integer()->max(7)->required(),
            'unit' => $schema->string()->enum(['c', 'f']),
        ]);
        $validator = new Validator();

        $this->assertSame([], $validator->validate(['city' => 'Leeds', 'days' => 3, 'unit' => 'c'], $definition));

        $errors = $validator->validate(['days' => 9, 'unit' => 'k'], $definition);

        $this->assertCount(3, $errors);
        $this->assertStringContainsString('[city] is required', $errors[0]);
        $this->assertStringContainsString('[days] must be at most 7', implode(' ', $errors));
        $this->assertStringContainsString('[unit] must be one of', implode(' ', $errors));

        $this->assertStringContainsString(
            'must be of type integer',
            $validator->validate(['city' => 'Leeds', 'days' => 'three'], $definition)[0]
        );
    }
}
