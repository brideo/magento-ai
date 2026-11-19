<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Schema;

/**
 * Checks data against the subset of JSON schema the SDK's builder produces.
 */
class Validator
{
    /**
     * The problems found, as sentences. An empty array means the data is valid.
     *
     * @param mixed $data
     * @param array<string,mixed> $schema
     * @param string $path
     * @return string[]
     */
    public function validate(mixed $data, array $schema, string $path = ''): array
    {
        if (isset($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $alternative) {
                if ($this->validate($data, $alternative, $path) === []) {
                    return [];
                }
            }

            return [sprintf('%s does not match any allowed shape.', $this->label($path))];
        }

        $types = (array) ($schema['type'] ?? []);
        if ($data === null && in_array('null', $types, true)) {
            return [];
        }

        $type = array_values(array_diff($types, ['null']))[0] ?? null;
        if ($type !== null && !$this->matchesType($data, $type)) {
            return [sprintf('%s must be of type %s.', $this->label($path), $type)];
        }

        $errors = [];

        if (isset($schema['enum']) && !in_array($data, $schema['enum'], true)) {
            $errors[] = sprintf(
                '%s must be one of: %s.',
                $this->label($path),
                implode(', ', array_map('strval', $schema['enum']))
            );
        }

        if ($type === 'object') {
            $data = (array) $data;
            foreach ($schema['required'] ?? [] as $required) {
                if (!array_key_exists($required, $data)) {
                    $errors[] = sprintf('%s is required.', $this->label($this->join($path, $required)));
                }
            }
            foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
                if (array_key_exists($name, $data)) {
                    $errors = [...$errors, ...$this->validate($data[$name], $property, $this->join($path, $name))];
                }
            }
        }

        if ($type === 'array' && isset($schema['items'])) {
            foreach (array_values($data) as $index => $item) {
                $errors = [...$errors, ...$this->validate($item, $schema['items'], $path . '[' . $index . ']')];
            }
        }

        return [...$errors, ...$this->checkBounds($data, $schema, $path)];
    }

    /**
     * Check the min/max keywords for numbers, strings and arrays.
     *
     * @param mixed $data
     * @param array<string,mixed> $schema
     * @param string $path
     * @return string[]
     */
    private function checkBounds(mixed $data, array $schema, string $path): array
    {
        $size = match (true) {
            is_int($data), is_float($data) => ['imum', $data],
            is_string($data) => ['Length', mb_strlen($data)],
            is_array($data) => ['Items', count($data)],
            default => null,
        };

        if ($size === null) {
            return [];
        }

        [$suffix, $value] = $size;
        $errors = [];

        if (isset($schema['min' . $suffix]) && $value < $schema['min' . $suffix]) {
            $errors[] = sprintf('%s must be at least %s.', $this->label($path), $schema['min' . $suffix]);
        }
        if (isset($schema['max' . $suffix]) && $value > $schema['max' . $suffix]) {
            $errors[] = sprintf('%s must be at most %s.', $this->label($path), $schema['max' . $suffix]);
        }

        return $errors;
    }

    /**
     * Whether a value has the given JSON schema type.
     *
     * @param mixed $data
     * @param string $type
     */
    private function matchesType(mixed $data, string $type): bool
    {
        return match ($type) {
            'string' => is_string($data),
            'integer' => is_int($data) || (is_float($data) && floor($data) === $data),
            'number' => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'array' => is_array($data) && array_is_list($data),
            'object' => is_array($data) || is_object($data),
            default => true,
        };
    }

    /**
     * Join a parent path and a property name.
     *
     * @param string $path
     * @param string $name
     */
    private function join(string $path, string $name): string
    {
        return $path === '' ? $name : $path . '.' . $name;
    }

    /**
     * How a path is named in a message.
     *
     * @param string $path
     */
    private function label(string $path): string
    {
        return $path === '' ? 'The value' : '[' . $path . ']';
    }
}
