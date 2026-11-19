<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Http;

use JsonException;

/**
 * A provider's HTTP response, kept so callers can read rate-limit headers and raw payloads.
 */
class Response
{
    /** @var array<string, string[]> */
    private array $headers = [];

    /** @var mixed */
    private mixed $decoded = null;

    /** @var bool */
    private bool $isDecoded = false;

    /**
     * @param int $status
     * @param array<string,string[]|string> $headers
     * @param string $body
     */
    public function __construct(
        private readonly int $status,
        array $headers,
        private readonly string $body
    ) {
        foreach ($headers as $name => $values) {
            $this->headers[strtolower((string) $name)] = (array) $values;
        }
    }

    /**
     * The HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * The raw response body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * The first value of a header, case-insensitively.
     *
     * @param string $name
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * Every header, keyed by lower-cased name.
     *
     * @return array<string, string[]>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * The decoded JSON body, or one value from it by dot path (e.g. "error.type").
     *
     * @param string|null $path
     * @param mixed $default
     */
    public function json(?string $path = null, mixed $default = null): mixed
    {
        if (!$this->isDecoded) {
            try {
                $this->decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->decoded = null;
            }
            $this->isDecoded = true;
        }

        if ($path === null) {
            return $this->decoded ?? $default;
        }

        $value = $this->decoded;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value ?? $default;
    }
}
