<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

/**
 * The provider is overloaded or temporarily unavailable.
 */
class ProviderOverloadedException extends AiException implements FailoverableException
{
}
