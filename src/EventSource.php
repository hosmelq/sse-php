<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use Generator;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

readonly class EventSource
{
    /**
     * The response body stream.
     */
    private StreamInterface $body;

    /**
     * The SSE decoder for parsing events.
     */
    private Decoder $decoder;

    /**
     * Create a new event source from an HTTP response.
     *
     * @throws SSEProtocolException
     */
    public function __construct(private ResponseInterface $response)
    {
        $this->decoder = new Decoder();
        $this->body = $this->response->getBody();

        $this->checkContentType();
    }

    /**
     * Destructor for automatic cleanup.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Close the event source and underlying stream.
     */
    public function close(): void
    {
        $this->body->close();
    }

    /**
     * Iterate over Server-Sent Events from the response with automatic cleanup.
     *
     * @return Generator<ServerSentEvent>
     */
    public function events(): Generator
    {
        try {
            while (! $this->body->eof()) {
                $line = Utils::readLine($this->body);

                if ($line === '') {
                    continue;
                }

                $line = mb_rtrim($line, "\r\n");
                $event = $this->decoder->decode($line);

                if ($event instanceof ServerSentEvent) {
                    yield $event;
                }
            }
        } finally {
            $this->close();
        }
    }

    /**
     * Get the last event ID that was received.
     */
    public function getLastEventId(): string
    {
        return $this->decoder->getLastEventId();
    }

    /**
     * Check that the response content type is text/event-stream.
     *
     * @throws SSEProtocolException
     */
    private function checkContentType(): void
    {
        $contentType = $this->response->getHeaderLine('content-type');

        if (! str_contains($contentType, 'text/event-stream')) {
            throw SSEProtocolException::invalidContentType($contentType);
        }
    }
}
