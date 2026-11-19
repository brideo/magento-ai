<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use Magento\Framework\Event\ManagerInterface;

/**
 * Publishes SDK events through Magento's event manager.
 *
 * The event name is "upturn_ai_" plus the snake-cased class name (AgentPrompted becomes
 * upturn_ai_agent_prompted) and the event object is passed under the "event" key.
 */
class Dispatcher
{
    /**
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        private readonly ManagerInterface $eventManager
    ) {
    }

    /**
     * Dispatch an event object.
     *
     * @param object $event
     */
    public function dispatch(object $event): void
    {
        $this->eventManager->dispatch(self::eventName($event), ['event' => $event]);
    }

    /**
     * The Magento event name for an event object or class.
     *
     * @param object|class-string $event
     */
    public static function eventName(object|string $event): string
    {
        $class = is_object($event) ? $event::class : $event;
        $base = substr($class, (int) strrpos($class, '\\') + 1);

        return 'upturn_ai_' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
    }
}
