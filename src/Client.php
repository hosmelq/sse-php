<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use InvalidArgumentException;

readonly class Client
{
    private ClientInterface $httpClient;

    public function __construct(null|ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws SSEConnectionException
     * @throws SSEProtocolException
     */
    public function connect(string $method, string $url, array $options = []): EventSource
    {
        $headers = $options['headers'] ?? [];

        if (! is_array($headers)) {
            throw new InvalidArgumentException('headers must be an array or null');
        }

        $lastEventId = '';

        foreach ($headers as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $lowercaseName = mb_strtolower($name);

            if (in_array($lowercaseName, ['accept', 'cache-control'], true)) {
                unset($headers[$name]);
            } elseif ($lowercaseName === 'last-event-id') {
                $value = is_array($value) ? reset($value) : $value;

                if (is_string($value) || is_int($value)) {
                    $lastEventId = mb_trim((string) $value, " \t", '8bit');
                }
            }
        }

        $headers['Accept'] = 'text/event-stream';
        $headers['Cache-Control'] = 'no-store';
        $options['headers'] = $headers;
        $options['stream'] = true;
        $options['http_errors'] = false;

        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (TransferException $transferException) {
            throw SSEConnectionException::connectionFailed($url, $transferException);
        }

        return new EventSource($response, $lastEventId);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): EventSource
    {
        return $this->connect('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $url, array $options = []): EventSource
    {
        return $this->connect('POST', $url, $options);
    }
}
