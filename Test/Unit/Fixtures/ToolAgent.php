<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Fixtures;

use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\HasMiddlewareInterface;
use UpturnStudio\Ai\Api\HasToolsInterface;
use UpturnStudio\Ai\Promptable;

class ToolAgent implements AgentInterface, HasToolsInterface, HasMiddlewareInterface
{
    use Promptable;

    /**
     * @param array<int, object> $tools
     * @param array<int, object|callable> $middleware
     */
    public function __construct(
        private readonly array $tools = [],
        private readonly array $middleware = []
    ) {
    }

    public function instructions(): string
    {
        return 'Use tools.';
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    public function middleware(): array
    {
        return $this->middleware;
    }
}
