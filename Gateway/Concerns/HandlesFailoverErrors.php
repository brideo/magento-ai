<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Gateway\Concerns;

use Closure;
use UpturnStudio\Ai\Exceptions\InsufficientCreditsException;
use UpturnStudio\Ai\Exceptions\InvalidApiKeyException;
use UpturnStudio\Ai\Exceptions\ProviderConnectionException;
use UpturnStudio\Ai\Exceptions\ProviderOverloadedException;
use UpturnStudio\Ai\Exceptions\RateLimitedException;
use UpturnStudio\Ai\Http\ConnectionException;
use UpturnStudio\Ai\Http\RequestException;

/**
 * Maps HTTP failures onto the SDK's exceptions, so the failover loop can tell which ones to move on from.
 *
 * Rate limits, overloads, exhausted credit and connection failures fail over. A bad API key and bad requests do not.
 */
trait HandlesFailoverErrors
{
    /**
     * Run a request, retrying short rate limits and translating failures.
     *
     * @param string $providerName
     * @param Closure $request
     */
    protected function withErrorHandling(string $providerName, Closure $request): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $request();
            } catch (ConnectionException $e) {
                throw new ProviderConnectionException(
                    sprintf('Could not connect to AI provider [%s]: %s', $providerName, $e->getMessage()),
                    0,
                    $e
                );
            } catch (RequestException $e) {
                $retryAfter = (int) $e->response->header('retry-after');
                $retryable = $e->response->status() === 429 || $this->isOverloaded($e);

                if ($retryable && $attempt < 2 && $retryAfter > 0 && $retryAfter <= 10 && !$this->isOutOfCredit($e)) {
                    $attempt++;
                    $this->pause($retryAfter);
                    continue;
                }

                throw $this->translate($providerName, $e);
            }
        }
    }

    /**
     * Choose the exception for a failed request.
     *
     * @param string $providerName
     * @param RequestException $e
     */
    protected function translate(string $providerName, RequestException $e): \Throwable
    {
        $status = $e->response->status();
        $detail = $this->errorMessage($e);

        if ($this->isInvalidApiKey($e)) {
            return new InvalidApiKeyException(
                sprintf('AI provider [%s] rejected the API key: %s', $providerName, $detail),
                $status,
                $e
            );
        }

        if ($this->isOutOfCredit($e)) {
            return new InsufficientCreditsException(
                sprintf('AI provider [%s] has insufficient credit or hit a spend limit: %s', $providerName, $detail),
                $status,
                $e
            );
        }

        if ($status === 429) {
            return new RateLimitedException(
                sprintf('AI provider [%s] is rate limited: %s', $providerName, $detail),
                $status,
                $e
            );
        }

        if ($this->isOverloaded($e)) {
            return new ProviderOverloadedException(
                sprintf('AI provider [%s] is overloaded: %s', $providerName, $detail),
                $status,
                $e
            );
        }

        return $e;
    }

    /**
     * Whether the failure means the API key was rejected. Gateways override this for providers that do not use 401.
     *
     * @param RequestException $e
     */
    protected function isInvalidApiKey(RequestException $e): bool
    {
        return $e->response->status() === 401;
    }

    /**
     * Whether the failure means the provider is overloaded or temporarily unavailable.
     *
     * @param RequestException $e
     */
    protected function isOverloaded(RequestException $e): bool
    {
        return in_array($e->response->status(), $this->overloadedStatuses(), true)
            || $this->errorType($e) === 'overloaded_error';
    }

    /**
     * Whether the failure means the account is out of credit or over a spend limit.
     *
     * @param RequestException $e
     */
    protected function isOutOfCredit(RequestException $e): bool
    {
        if ($e->response->status() === 402) {
            return true;
        }

        $codes = [
            'insufficient_quota',
            'billing_error',
            'credit_balance_exhausted',
            'organization_spend_limit_exceeded',
            'project_spend_limit_exceeded',
            'organization_usage_limit_exceeded',
        ];

        if (in_array($this->errorType($e), $codes, true)
            || in_array((string) $e->response->json('error.code', ''), $codes, true)
        ) {
            return true;
        }

        $message = strtolower($this->errorMessage($e));
        foreach ($this->insufficientCreditPatterns() as $pattern) {
            if (str_contains($message, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * HTTP statuses that mean "overloaded". Gateways override this to add provider-specific ones.
     *
     * @return int[]
     */
    protected function overloadedStatuses(): array
    {
        return [502, 503, 504, 520, 522, 524, 529];
    }

    /**
     * Phrases in an error message that mean the account is out of credit.
     *
     * @return string[]
     */
    protected function insufficientCreditPatterns(): array
    {
        return [];
    }

    /**
     * The provider's error type, e.g. "rate_limit_error".
     *
     * @param RequestException $e
     */
    protected function errorType(RequestException $e): string
    {
        return (string) $e->response->json('error.type', '');
    }

    /**
     * The provider's error message.
     *
     * @param RequestException $e
     */
    protected function errorMessage(RequestException $e): string
    {
        $message = $e->response->json('error.message');

        return is_string($message) && $message !== '' ? $message : mb_substr($e->response->body(), 0, 300);
    }

    /**
     * Wait before a retry. Separate so tests can skip the wait.
     *
     * @param int $seconds
     */
    protected function pause(int $seconds): void
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        sleep($seconds);
    }
}
