<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Api\Gateway;

use UpturnStudio\Ai\Api\Provider\TextProviderInterface;
use UpturnStudio\Ai\Gateway\StepResponse;
use UpturnStudio\Ai\Gateway\TextGenerationOptions;
use UpturnStudio\Ai\Messages\Message;

/**
 * The wire adapter for one vendor's text API. A gateway makes exactly one model round-trip; the tool loop
 * lives in TextGenerationLoop.
 */
interface TextGatewayInterface
{
    /**
     * Send one request and parse the reply.
     *
     * @param TextProviderInterface $provider
     * @param string $model
     * @param string|null $instructions
     * @param Message[] $messages
     * @param array<int,object> $tools
     * @param array<string,mixed>|null $schema A complete JSON schema when the output must be structured.
     * @param TextGenerationOptions $options
     * @param int|null $timeout Seconds.
     */
    public function generateTextStep(
        TextProviderInterface $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        TextGenerationOptions $options,
        ?int $timeout = null
    ): StepResponse;
}
