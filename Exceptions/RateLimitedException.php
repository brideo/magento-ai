<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

/**
 * The provider rejected the request because of a rate limit.
 */
class RateLimitedException extends AiException implements FailoverableException
{
}
