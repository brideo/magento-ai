<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\TestCase;
use UpturnStudio\Ai\Ai;
use UpturnStudio\Ai\Events\Dispatcher;
use UpturnStudio\Ai\Http\Client;
use UpturnStudio\Ai\Model\Config\ArrayConfig;
use UpturnStudio\Ai\Model\StandaloneFactory;

/**
 * Wires the SDK without Magento, with a mocked HTTP layer and a recording event manager.
 */
abstract class AiTestCase extends TestCase
{
    /** @var array<int, array<string, mixed>> Guzzle transaction history. */
    protected array $history = [];

    /** @var string[] Names of the Magento events dispatched. */
    protected array $events = [];

    protected MockHandler $mock;

    protected function setUp(): void
    {
        $this->history = [];
        $this->events = [];
        $this->mock = new MockHandler();

        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        $events = &$this->events;
        $manager = new class ($events) implements ManagerInterface {
            /** @param string[] $events */
            public function __construct(private array &$events)
            {
            }

            public function dispatch($eventName, array $data = [])
            {
                $this->events[] = $eventName;
            }
        };

        [$aiManager, $runner] = StandaloneFactory::create(
            new ArrayConfig(
                ['anthropic' => ['driver' => 'anthropic', 'key' => 'sk-configured']],
                ['text' => 'anthropic']
            ),
            new Client(new GuzzleClient(['handler' => $stack])),
            new Dispatcher($manager)
        );

        Ai::swap($aiManager, $runner);
    }

    protected function tearDown(): void
    {
        Ai::reset();
    }

    /**
     * Queue a JSON HTTP response.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    protected function queueJson(array $body, int $status = 200, array $headers = []): void
    {
        $this->mock->append(new Response($status, $headers, (string) json_encode($body)));
    }

    /**
     * The decoded JSON body of a sent request.
     *
     * @return array<string, mixed>
     */
    protected function sentBody(int $index = 0): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }

    /**
     * A minimal Anthropic Messages API text response.
     *
     * @return array<string, mixed>
     */
    protected function anthropicText(string $text, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stopReason,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ];
    }
}
