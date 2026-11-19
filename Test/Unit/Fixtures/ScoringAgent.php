<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Test\Unit\Fixtures;

use UpturnStudio\Ai\Api\AgentInterface;
use UpturnStudio\Ai\Api\HasStructuredOutputInterface;
use UpturnStudio\Ai\Attributes\MaxTokens;
use UpturnStudio\Ai\Attributes\Model;
use UpturnStudio\Ai\Attributes\Provider;
use UpturnStudio\Ai\Enums\Lab;
use UpturnStudio\Ai\Promptable;
use UpturnStudio\Ai\Schema\JsonSchema;

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-5-5')]
#[MaxTokens(2048)]
class ScoringAgent implements AgentInterface, HasStructuredOutputInterface
{
    use Promptable;

    public function instructions(): string
    {
        return 'Score the transcript.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback' => $schema->string()->required(),
            'score' => $schema->integer()->min(1)->max(10)->required(),
        ];
    }
}
