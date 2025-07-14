<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use GuzzleHttp\Client as HttpClient;
use Throwable;

readonly class Client
{
    /**
     * The HTTP client used for requests.
     */
    private HttpClient $httpClient;

    /**
     * Create a new SSE client.
     */
    public function __construct(?HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * Connect to an SSE endpoint using the specified HTTP method.
     *
     * @param array<string, mixed> $options
     *
     * @throws SSEConnectionException
     * @throws SSEProtocolException
     */
    public function connect(string $method, string $url, array $options = []): EventSource
    {
        $optionsHeaders = $options['headers'] ?? [];

        if (! is_array($optionsHeaders)) {
            $optionsHeaders = [];
        }

        $headers = array_merge($optionsHeaders, [
            'Accept' => 'text/event-stream',
            'Cache-Control' => 'no-store',
        ]);

        $requestOptions = array_merge($options, [
            'headers' => $headers,
            'read_timeout' => $options['read_timeout'] ?? 10,
            'stream' => true,
            'timeout' => $options['timeout'] ?? 0,
        ]);

        try {
            $response = $this->httpClient->request($method, $url, $requestOptions);
        } catch (Throwable $throwable) {
            throw SSEConnectionException::connectionFailed($url, $throwable);
        }

        return new EventSource($response);
    }

    /**
     * Connect to an SSE endpoint using GET method.
     *
     * @param array<string, mixed> $options
     *
     * @throws SSEConnectionException
     * @throws SSEProtocolException
     */
    public function get(string $url, array $options = []): EventSource
    {
        return $this->connect('GET', $url, $options);
    }

    /**
     * Connect to an SSE endpoint using POST method.
     *
     * @param array<string, mixed> $options
     *
     * @throws SSEConnectionException
     * @throws SSEProtocolException
     */
    public function post(string $url, array $options = []): EventSource
    {
        return $this->connect('POST', $url, $options);
    }
}
