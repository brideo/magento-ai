<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

use Throwable;

/**
 * Marks a provider failure that should move on to the next provider in a failover list.
 */
interface FailoverableException extends Throwable
{
}
