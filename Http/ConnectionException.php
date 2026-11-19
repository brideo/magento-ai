<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Http;

use UpturnStudio\Ai\Exceptions\AiException;

/**
 * The request never produced a response: DNS failure, refused connection or timeout.
 */
class ConnectionException extends AiException
{
}
