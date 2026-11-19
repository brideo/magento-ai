<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Messages;

/**
 * One message of conversation history.
 */
class Message
{
    /** @var MessageRole */
    public readonly MessageRole $role;

    /**
     * @param MessageRole|string $role
     * @param string $content
     */
    public function __construct(
        MessageRole|string $role,
        public readonly string $content = ''
    ) {
        $this->role = $role instanceof MessageRole ? $role : MessageRole::from($role);
    }

    /**
     * Build the right message class from a role and content, or pass a message through unchanged.
     *
     * @param Message|array{role:string,content:string} $message
     */
    public static function from(Message|array $message): Message
    {
        if ($message instanceof Message) {
            return $message;
        }

        return match (MessageRole::from($message['role'])) {
            MessageRole::User => new UserMessage($message['content'] ?? ''),
            MessageRole::Assistant => new AssistantMessage($message['content'] ?? ''),
            MessageRole::ToolResult => new ToolResultMessage($message['tool_results'] ?? []),
        };
    }
}
