<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Http;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use UpturnStudio\Ai\Http\SseParser;

/**
 * @covers \UpturnStudio\Ai\Http\SseParser
 */
class SseParserTest extends TestCase
{
    public function testParsesNamedEventsCommentsAndMultiLineData(): void
    {
        $body = "event: message_start\r\ndata: {\"a\":1}\r\n\r\n"
            . ": keep-alive comment\n\n"
            . "data: line one\ndata: line two\n\n"
            . "event: message_stop\ndata: {}";

        $events = iterator_to_array((new SseParser())->parse(Utils::streamFor($body)), false);

        $this->assertSame([
            ['event' => 'message_start', 'data' => '{"a":1}'],
            ['event' => null, 'data' => "line one\nline two"],
            ['event' => 'message_stop', 'data' => '{}'],
        ], $events);
    }
}
