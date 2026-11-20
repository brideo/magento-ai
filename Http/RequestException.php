<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Http;

use UpturnStudio\Ai\Exceptions\AiException;

/**
 * The provider answered with an error status (4xx or 5xx).
 */
class RequestException extends AiException
{
    /**
     * @param Response $response
     * @param string|null $message
     */
    public function __construct(
        public readonly Response $response,
        ?string $message = null
    ) {
        $detail = $response->json('error.message') ?? $response->json('0.error.message');

        parent::__construct(
            $message ?? sprintf(
                'HTTP request returned status code %d: %s',
                $response->status(),
                is_string($detail) && $detail !== '' ? $detail : mb_substr($response->body(), 0, 500)
            ),
            $response->status()
        );
    }
}
