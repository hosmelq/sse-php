<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use Generator;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class EventSource
{
    private const int CHUNK_SIZE = 8_192;

    private readonly StreamInterface $body;

    private bool $closed = false;

    private bool $consumed = false;

    private readonly Decoder $decoder;

    private readonly bool $readsSocket;

    /**
     * Create an event source that resumes after the given event ID, the one the request sent in
     * its Last-Event-ID header.
     *
     * @throws SSEProtocolException
     */
    public function __construct(
        private readonly ResponseInterface $response,
        string $lastEventId = '',
    ) {
        if ($response->getStatusCode() !== 200) {
            throw SSEProtocolException::invalidStatus($response);
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $mediaType = mb_trim(explode(';', $contentType, 2)[0]);

        if (! str_contains(mb_strtolower($mediaType), 'text/event-stream')) {
            throw SSEProtocolException::invalidContentType($response);
        }

        $this->body = $response->getBody();
        $this->decoder = new Decoder($lastEventId);
        $streamType = $this->body->getMetadata('stream_type');
        $this->readsSocket = $this->body instanceof Stream
            && is_string($streamType)
            && str_contains($streamType, 'socket');
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->body->close();
    }

    /**
     * @return Generator<int, ServerSentEvent>
     */
    public function events(): Generator
    {
        if ($this->consumed || $this->closed) {
            throw new SSEException('The event stream has already been consumed or closed.');
        }

        $this->consumed = true;
        $lines = new LineSplitter();

        try {
            while (($chunk = $this->read()) !== null) {
                if ($chunk === '') {
                    continue;
                }

                foreach ($lines->split($chunk) as $line) {
                    $event = $this->decoder->decode($line);

                    if ($event instanceof ServerSentEvent) {
                        yield $event;
                    }
                }
            }
        } finally {
            $this->close();
        }
    }

    public function getLastEventId(): string
    {
        return $this->decoder->getLastEventId();
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the reconnection time the server sent last, in milliseconds.
     */
    public function getRetry(): null|int
    {
        return $this->decoder->getRetry();
    }

    private function read(): null|string
    {
        if ($this->closed) {
            return null;
        }

        try {
            if ($this->body->eof()) {
                return null;
            }

            return $this->readsSocket
                ? $this->readAvailable()
                : $this->body->read(self::CHUNK_SIZE);
        } catch (RuntimeException $runtimeException) {
            throw SSEConnectionException::readFailed($runtimeException);
        }
    }

    /**
     * Read what a PHP socket stream has available without waiting for more.
     *
     * PHP decodes chunked HTTP bodies through a stream filter, and a filtered read waits until it
     * can return the requested length. A one-byte read returns as soon as data arrives, and the
     * bytes PHP buffered along with it can then be read without waiting.
     */
    private function readAvailable(): string
    {
        $chunk = $this->body->read(1);
        $unread = $this->body->getMetadata('unread_bytes');

        if (is_int($unread) && $unread > 0) {
            $chunk .= $this->body->read($unread);
        }

        return $chunk;
    }
}
