<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

/**
 * The provider account has run out of credit or hit a spend limit.
 */
class InsufficientCreditsException extends AiException implements FailoverableException
{
}
