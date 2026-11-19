<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Fixtures;

use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Promptable;

class SalesCoach implements AgentInterface
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach.';
    }
}
