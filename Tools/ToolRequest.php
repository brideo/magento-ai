<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Tools;

use ArrayAccess;
use LogicException;
use UpturnStudio\Ai\Exceptions\ToolValidationException;
use UpturnStudio\Ai\Schema\Validator;

/**
 * The arguments the model passed to a tool. Read them like an array: `$request['city']`.
 *
 * @implements ArrayAccess<string, mixed>
 */
class ToolRequest implements ArrayAccess
{
    /**
     * @param array<string,mixed> $arguments
     * @param string|null $toolCallId
     * @param array<string,mixed>|null $schema The tool's own argument schema.
     */
    public function __construct(
        private readonly array $arguments = [],
        public readonly ?string $toolCallId = null,
        private readonly ?array $schema = null
    ) {
    }

    /**
     * Every argument.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->arguments;
    }

    /**
     * One argument, or a default.
     *
     * @param string $key
     * @param mixed $default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    /**
     * An argument as a string.
     *
     * @param string $key
     * @param string $default
     */
    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->arguments[$key] ?? $default);
    }

    /**
     * An argument as an integer.
     *
     * @param string $key
     * @param int $default
     */
    public function integer(string $key, int $default = 0): int
    {
        return (int) ($this->arguments[$key] ?? $default);
    }

    /**
     * An argument as a boolean.
     *
     * @param string $key
     * @param bool $default
     */
    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->arguments[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check the arguments against the tool's schema and return them.
     *
     * When they do not match, the problems are returned to the model as the tool result so it can retry.
     *
     * @return array<string, mixed>
     * @throws ToolValidationException
     */
    public function validate(): array
    {
        if ($this->schema !== null) {
            $errors = (new Validator())->validate($this->arguments, $this->schema);
            if ($errors !== []) {
                throw new ToolValidationException($errors);
            }
        }

        return $this->arguments;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->arguments[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->arguments[$offset] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Tool requests are read-only.');
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Tool requests are read-only.');
    }
}
