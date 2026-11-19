<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Http;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Turns a streamed HTTP body into server-sent events, without waiting for the body to finish.
 */
class SseParser
{
    /**
     * Yield each event as soon as its terminating blank line has been read.
     *
     * @param StreamInterface $body
     * @return Generator<int, array{event: ?string, data: string}>
     */
    public function parse(StreamInterface $body): Generator
    {
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                continue;
            }

            $buffer .= str_replace("\r\n", "\n", $chunk);

            while (($end = strpos($buffer, "\n\n")) !== false) {
                $event = $this->parseEvent(substr($buffer, 0, $end));
                $buffer = substr($buffer, $end + 2);
                if ($event !== null) {
                    yield $event;
                }
            }
        }

        $event = $this->parseEvent($buffer);
        if ($event !== null) {
            yield $event;
        }
    }

    /**
     * Parse one event block. Comment lines and blocks without data are dropped.
     *
     * @param string $block
     * @return array{event: ?string, data: string}|null
     */
    private function parseEvent(string $block): ?array
    {
        $name = null;
        $data = [];

        foreach (explode("\n", $block) as $line) {
            if ($line === '' || $line[0] === ':') {
                continue;
            }

            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = ltrim($value, ' ');

            if ($field === 'event') {
                $name = $value;
            } elseif ($field === 'data') {
                $data[] = $value;
            }
        }

        return $data === [] ? null : ['event' => $name, 'data' => implode("\n", $data)];
    }
}
