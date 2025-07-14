<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

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
     * The last event ID received.
     */
    private string $lastEventId = '';

    /**
     * The current retry time in milliseconds.
     */
    private ?int $retry = null;

    /**
     * Decode a line of SSE data.
     */
    public function decode(string $line): ?ServerSentEvent
    {
        if ($line === '') {
            return $this->dispatchEvent();
        }

        if (str_starts_with($line, ':')) {
            return null;
        }

        $colonPos = mb_strpos($line, ':');

        if ($colonPos === false) {
            $fieldName = $line;
            $value = '';
        } else {
            $fieldName = mb_substr($line, 0, $colonPos);
            $value = mb_substr($line, $colonPos + 1);

            if (str_starts_with($value, ' ')) {
                $value = mb_substr($value, 1);
            }
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
                    $this->lastEventId = $value;
                }

                break;

            case 'retry':
                if (is_numeric($value) && (int) $value >= 0) {
                    $this->retry = (int) $value;
                }

                break;

            default:
                break;
        }

        return null;
    }

    /**
     * Get the last event ID that was received.
     */
    public function getLastEventId(): string
    {
        return $this->lastEventId;
    }

    /**
     * Dispatch the current event if any fields were set.
     */
    private function dispatchEvent(): ?ServerSentEvent
    {
        if ($this->data === [] && $this->event === '' && $this->lastEventId === '' && $this->retry === null) {
            return null;
        }

        $data = implode("\n", $this->data);

        $event = new ServerSentEvent(
            data: $data,
            event: $this->event !== '' ? $this->event : null,
            id: $this->lastEventId,
            retry: $this->retry
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
