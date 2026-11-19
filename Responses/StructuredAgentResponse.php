<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Responses;

use ArrayAccess;
use JsonSerializable;
use LogicException;

/**
 * The response of an agent with an output schema. Read it like an array: `$response['score']`.
 *
 * @implements ArrayAccess<string, mixed>
 */
class StructuredAgentResponse extends AgentResponse implements ArrayAccess, JsonSerializable
{
    /**
     * The structured output as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->structured ?? [];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->toArray()[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Structured responses are read-only.');
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Structured responses are read-only.');
    }
}
