<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api;

use UpturnStudio\Ai\Messages\Message;

/**
 * An agent that supplies its own conversation history.
 */
interface ConversationalInterface
{
    /**
     * The messages that came before the new prompt, oldest first.
     *
     * @return iterable<Message>
     */
    public function messages(): iterable;
}
