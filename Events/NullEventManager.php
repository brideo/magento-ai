<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Events;

use Magento\Framework\Event\ManagerInterface;

/**
 * An event manager that drops every event. Used when the SDK runs without Magento's ObjectManager, as in unit tests.
 */
class NullEventManager implements ManagerInterface
{
    /**
     * @inheritDoc
     */
    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    public function dispatch($eventName, array $data = [])
    {
    }
}
