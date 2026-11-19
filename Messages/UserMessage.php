<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Messages;

/**
 * A message from the user, with any files attached to it.
 */
class UserMessage extends Message
{
    /**
     * @param string $content
     * @param array<int,object> $attachments
     */
    public function __construct(
        string $content,
        public readonly array $attachments = []
    ) {
        parent::__construct(MessageRole::User, $content);
    }
}
