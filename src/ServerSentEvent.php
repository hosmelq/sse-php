<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use function Safe\json_decode;

use JsonSerializable;

readonly class ServerSentEvent implements JsonSerializable
{
    /**
     * The event data.
     */
    public string $data;

    /**
     * The event type.
     */
    public string $event;

    /**
     * The event ID.
     */
    public string $id;

    /**
     * Create a new server-sent event.
     */
    public function __construct(
        ?string $data = null,
        ?string $event = null,
        ?string $id = null,
        public ?int $retry = null
    ) {
        $this->data = $data ?? '';
        $this->event = $event !== null && $event !== '' ? $event : 'message';
        $this->id = $id ?? '';
    }

    /**
     * Parse the event data as JSON.
     */
    public function json(): mixed
    {
        return json_decode($this->data, true);
    }

    /**
     * JSON representation of the event.
     *
     * @return array{data: string, event: string, id: string, retry: null|int}
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
            'event' => $this->event,
            'id' => $this->id,
            'retry' => $this->retry,
        ];
    }
}
