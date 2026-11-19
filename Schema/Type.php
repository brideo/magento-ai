<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Schema;

use InvalidArgumentException;

/**
 * One node of a JSON schema, built fluently: `$schema->integer()->min(1)->max(10)->required()`.
 *
 * The method names match Laravel's JSON schema builder so examples carry across unchanged.
 */
class Type
{
    /** @var bool */
    private bool $required = false;

    /** @var bool */
    private bool $nullable = false;

    /** @var array<string, mixed> */
    private array $keywords = [];

    /** @var array<string, Type> */
    private array $properties = [];

    /** @var Type|null */
    private ?Type $items = null;

    /** @var Type[] */
    private array $anyOf = [];

    /**
     * @param string $kind One of string, integer, number, boolean, array, object, anyOf.
     */
    public function __construct(
        private readonly string $kind
    ) {
    }

    /**
     * Mark the property as required on its parent object.
     *
     * @param bool $required
     */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Whether the parent object must list this property as required.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Allow null as well as the declared type.
     *
     * @param bool $nullable
     */
    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Describe the value for the model.
     *
     * @param string $description
     */
    public function description(string $description): static
    {
        $this->keywords['description'] = $description;

        return $this;
    }

    /**
     * A human-readable title.
     *
     * @param string $title
     */
    public function title(string $title): static
    {
        $this->keywords['title'] = $title;

        return $this;
    }

    /**
     * Restrict the value to a fixed list. A backed enum class name is expanded to its values.
     *
     * @param array<int,string|int|float|bool>|class-string<\BackedEnum> $values
     */
    public function enum(array|string $values): static
    {
        if (is_string($values)) {
            if (!enum_exists($values) || !is_subclass_of($values, \BackedEnum::class)) {
                throw new InvalidArgumentException(sprintf('[%s] is not a backed enum.', $values));
            }
            $values = array_map(static fn (\BackedEnum $case) => $case->value, $values::cases());
        }

        $this->keywords['enum'] = array_values($values);

        return $this;
    }

    /**
     * The default value.
     *
     * @param mixed $value
     */
    public function default(mixed $value): static
    {
        $this->keywords['default'] = $value;

        return $this;
    }

    /**
     * Lower bound: minimum for numbers, minLength for strings, minItems for arrays.
     *
     * @param int|float $value
     */
    public function min(int|float $value): static
    {
        $this->keywords[$this->boundKeyword('min')] = $value;

        return $this;
    }

    /**
     * Upper bound: maximum for numbers, maxLength for strings, maxItems for arrays.
     *
     * @param int|float $value
     */
    public function max(int|float $value): static
    {
        $this->keywords[$this->boundKeyword('max')] = $value;

        return $this;
    }

    /**
     * A string format such as "date", "date-time", "email" or "uri".
     *
     * @param string $format
     */
    public function format(string $format): static
    {
        $this->keywords['format'] = $format;

        return $this;
    }

    /**
     * A regular expression the string must match.
     *
     * @param string $pattern
     */
    public function pattern(string $pattern): static
    {
        $this->keywords['pattern'] = $pattern;

        return $this;
    }

    /**
     * The schema every element of an array must match.
     *
     * @param Type $items
     */
    public function items(Type $items): static
    {
        $this->items = $items;

        return $this;
    }

    /**
     * The properties of an object.
     *
     * @param array<string,Type> $properties
     */
    public function properties(array $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * The alternatives of an anyOf.
     *
     * @param Type[] $types
     */
    public function alternatives(array $types): static
    {
        $this->anyOf = $types;

        return $this;
    }

    /**
     * Render the node as a JSON schema array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->kind === 'anyOf') {
            $alternatives = array_map(static fn (Type $type) => $type->toArray(), $this->anyOf);
            if ($this->nullable) {
                $alternatives[] = ['type' => 'null'];
            }

            return ['anyOf' => $alternatives] + $this->keywords;
        }

        $schema = ['type' => $this->nullable ? [$this->kind, 'null'] : $this->kind] + $this->keywords;

        if ($this->kind === 'array' && $this->items !== null) {
            $schema['items'] = $this->items->toArray();
        }

        if ($this->kind === 'object') {
            $schema += JsonSchema::objectKeywords($this->properties);
        }

        return $schema;
    }

    /**
     * Pick the JSON schema keyword a min()/max() call means for this kind of node.
     *
     * @param string $bound
     */
    private function boundKeyword(string $bound): string
    {
        return match ($this->kind) {
            'string' => $bound . 'Length',
            'array' => $bound . 'Items',
            'integer', 'number' => $bound . 'imum',
            default => throw new InvalidArgumentException(
                sprintf('%s() is not supported on a schema of type [%s].', $bound, $this->kind)
            ),
        };
    }
}
