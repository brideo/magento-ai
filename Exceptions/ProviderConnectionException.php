<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

/**
 * The provider could not be reached.
 */
class ProviderConnectionException extends AiException implements FailoverableException
{
}
