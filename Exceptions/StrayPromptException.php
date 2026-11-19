<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

/**
 * An agent was prompted in a test without a fake response to return.
 */
class StrayPromptException extends AiException
{
}
