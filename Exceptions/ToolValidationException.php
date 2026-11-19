<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Exceptions;

/**
 * A tool's arguments did not match its schema. The message goes back to the model so it can try again.
 */
class ToolValidationException extends AiException
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly array $errors
    ) {
        parent::__construct('The tool arguments are invalid: ' . implode(' ', $errors));
    }
}
