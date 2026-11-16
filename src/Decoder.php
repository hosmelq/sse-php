<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * @internal
 */
class Decoder
{
    /**
     * The current event data lines.
     *
     * @var list<string>
     */
    private array $data = [];

    /**
     * The current event type.
     */
    private string $event = '';

    /**
     * The event ID the stream set, which becomes the last event ID when the event ends.
     */
    private string $idBuffer;

    /**
     * The reconnection time the stream set last, in milliseconds.
     */
    private null|int $reconnectionTime = null;

    /**
     * The reconnection time the current event set, in milliseconds.
     */
    private null|int $retry = null;

    /**
     * Create a decoder that resumes after the given event ID.
     */
    public function __construct(private string $lastEventId = '')
    {
        $this->idBuffer = $lastEventId;
    }

    /**
     * Decode a line of SSE data.
     */
    public function decode(string $line): null|ServerSentEvent
    {
        if ($line === '') {
            return $this->dispatchEvent();
        }

        if (str_starts_with($line, ':')) {
            return null;
        }

        [$fieldName, $value] = array_pad(explode(':', $line, 2), 2, '');

        if (str_starts_with($value, ' ')) {
            $value = preg_replace('/\A /', '', $value);
        }

        switch ($fieldName) {
            case 'data':
                $this->data[] = $value;

                break;

            case 'event':
                $this->event = $value;

                break;

            case 'id':
                if (! str_contains($value, "\0")) {
                    $this->idBuffer = $value;
                }

                break;

            case 'retry':
                if (preg_match('/\A[0-9]+\z/', $value) === 1) {
                    $this->retry = (int) $value;
                    $this->reconnectionTime = $this->retry;
                }

                break;

            default:
                break;
        }

        return null;
    }

    /**
     * Get the ID of the last event that ended.
     */
    public function getLastEventId(): string
    {
        return $this->lastEventId;
    }

    /**
     * Get the reconnection time the stream set last, in milliseconds.
     */
    public function getRetry(): null|int
    {
        return $this->reconnectionTime;
    }

    /**
     * End the current event, and return it when it has data.
     */
    private function dispatchEvent(): null|ServerSentEvent
    {
        $this->lastEventId = $this->idBuffer;

        if ($this->data === []) {
            $this->reset();

            return null;
        }

        $event = new ServerSentEvent(
            data: implode("\n", $this->data),
            event: $this->event !== '' ? $this->event : null,
            id: $this->lastEventId,
            retry: $this->retry,
        );

        $this->reset();

        return $event;
    }

    /**
     * Reset the decoder state for the next event.
     */
    private function reset(): void
    {
        $this->data = [];
        $this->event = '';
        $this->retry = null;
    }
}
