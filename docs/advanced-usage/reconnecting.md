---
title: "Reconnecting"
description: "Resume a stream after it drops by sending the last event ID back to the server and waiting the reconnection time it asked for."
weight: 2
---

The package does not reconnect by itself, because when and how often to retry depends on your application. Servers that support resuming give their events an `id` and read the `Last-Event-ID` header of the next request to continue after that event. They can also send a `retry` field, the number of milliseconds to wait before reconnecting.

This loop suits streams that should stay open, such as notification feeds. It reconnects after a failure or when the stream ends, and resumes after the last event it received:

```php
use HosmelQ\SSE\Client;
use HosmelQ\SSE\SSEConnectionException;

$client = new Client();
$lastEventId = '';
$retry = 3000;

while (true) {
    $source = null;

    try {
        $source = $client->get('https://example.com/events', [
            'headers' => $lastEventId === '' ? [] : ['Last-Event-ID' => $lastEventId],
            'read_timeout' => 300,
        ]);

        foreach ($source->events() as $event) {
            handleEvent($event);
        }
    } catch (SSEConnectionException) {
        // Reconnect below.
    }

    if ($source !== null) {
        $lastEventId = $source->getLastEventId();
        $retry = $source->getRetry() ?? $retry;
    }

    usleep($retry * 1000);
}
```

The stream starts with the ID passed in the request's `Last-Event-ID` header, so `getLastEventId()` keeps it across reconnections until the server sends another one. It only counts events that ended, so a connection that drops in the middle of an event resumes before that event.

The loop leaves `SSEProtocolException` uncaught. That exception means the server answered with something other than an event stream, such as an authentication error or `204 No Content`, and a new request would most likely get the same answer.

When the server may stay unreachable for a long time, add a limit to the attempts or grow the wait after each failure. Streams that end on their own, such as an API's streamed response, should not reconnect at all.
