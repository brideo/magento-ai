<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

/**
 * An agent that can call tools.
 */
interface HasToolsInterface
{
    /**
     * The tools available to the agent.
     *
     * @return iterable<object>
     */
    public function tools(): iterable;
}
