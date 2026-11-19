<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

namespace UpturnStudio\Ai\Http;

use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * The single place the SDK talks HTTP. Every gateway goes through it, which is what makes them testable
 * with a Guzzle MockHandler.
 */
class Client
{
    /** @var GuzzleClientInterface */
    private GuzzleClientInterface $client;

    /**
     * @param GuzzleClientInterface|null $client
     * @param SseParser|null $sseParser
     */
    public function __construct(
        ?GuzzleClientInterface $client = null,
        private ?SseParser $sseParser = null
    ) {
        $this->client = $client ?? new GuzzleClient();
        $this->sseParser ??= new SseParser();
    }

    /**
     * POST a JSON body and return the response.
     *
     * @param string $url
     * @param array<string,string> $headers
     * @param array<string,mixed> $body
     * @param int $timeout Seconds.
     * @throws RequestException When the provider answers with a 4xx or 5xx status.
     * @throws ConnectionException When no response arrives.
     */
    public function postJson(string $url, array $headers, array $body, int $timeout = 60): Response
    {
        return $this->send('POST', $url, [
            RequestOptions::HEADERS => $headers,
            RequestOptions::JSON => $body,
            RequestOptions::TIMEOUT => $timeout,
        ]);
    }

    /**
     * POST a JSON body and yield the server-sent events of the response as they arrive.
     *
     * @param string $url
     * @param array<string,string> $headers
     * @param array<string,mixed> $body
     * @param int $timeout Seconds.
     * @return Generator<int, array{event: ?string, data: string}>
     * @throws RequestException
     * @throws ConnectionException
     */
    public function streamJson(string $url, array $headers, array $body, int $timeout = 60): Generator
    {
        try {
            $response = $this->client->request('POST', $url, [
                RequestOptions::HEADERS => $headers + ['Accept' => 'text/event-stream'],
                RequestOptions::JSON => $body,
                RequestOptions::TIMEOUT => $timeout,
                RequestOptions::CONNECT_TIMEOUT => 10,
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::STREAM => true,
            ]);
        } catch (GuzzleException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() >= 400) {
            throw new RequestException(
                new Response($response->getStatusCode(), $response->getHeaders(), (string) $response->getBody())
            );
        }

        yield from $this->sseParser->parse($response->getBody());
    }

    /**
     * Send a request with arbitrary Guzzle options (multipart uploads, GET, DELETE).
     *
     * @param string $method
     * @param string $url
     * @param array<string,mixed> $options
     * @throws RequestException
     * @throws ConnectionException
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        try {
            $response = $this->client->request($method, $url, $options + [
                RequestOptions::CONNECT_TIMEOUT => 10,
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (GuzzleException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }

        $result = new Response($response->getStatusCode(), $response->getHeaders(), (string) $response->getBody());

        if ($result->status() >= 400) {
            throw new RequestException($result);
        }

        return $result;
    }
}
