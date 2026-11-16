---
title: "Reading Events"
description: "Iterate the events of a stream, read their fields and the stream's last event ID, decode JSON data, and stop reading early."
weight: 2
---

`events()` yields a `ServerSentEvent` for each event in the stream, as soon as the server sends it. The loop ends when the server closes the stream.

```php
foreach ($source->events() as $event) {
    $event->event; // The event type, "message" unless the event sets one
    $event->data;  // The data, with multiple "data" lines joined by "\n"
    $event->id;    // The last event ID, or ""
    $event->retry; // The reconnection time the event set, in milliseconds, or null
}
```

For example, this event:

```text
event: order.shipped
id: 42
data: {"order": 1001}

```

has the type `order.shipped`, the ID `42`, and the data `{"order": 1001}`. A blank line ends each event, and only events with at least one `data` line are yielded, as in browsers. Lines that start with a colon are comments, such as keep-alive pings, and never become events.

## Event IDs and reconnection times

An event's `id` is the last event ID of the stream, so events without an `id` field keep the previous one, and an empty `id` field clears it. An event that only sets `id` or `retry` updates the stream without being yielded. `getLastEventId()` and `getRetry()` return what the stream set, including after the loop:

```php
$source->getLastEventId(); // "42"
$source->getRetry(); // The last reconnection time in milliseconds, or null
```

When you pass a `Last-Event-ID` header with the request, the stream starts with that ID. Headers your Guzzle client adds to every request don't count, so pass this one with each request. [Reconnecting](../advanced-usage/reconnecting) uses both values to resume a stream.

## JSON data

`json()` decodes the data into arrays:

```php
$payload = $event->json();

$payload['order']; // 1001
```

It throws `JsonException` when the data is not valid JSON. Some APIs end their streams with a sentinel that is not JSON, such as `[DONE]`, so check the data first:

```php
foreach ($source->events() as $event) {
    if ($event->data === '[DONE]') {
        break;
    }

    $chunk = $event->json();
}
```

## Stopping early

Breaking out of a `foreach` over `events()` closes the connection. Outside a loop, call `close()`. A stream can be read once: iterating it again throws `SSEException`.

## The response

`getResponse()` returns the PSR-7 response, for example to read a header the server sent with the stream:

```php
$requestId = $source->getResponse()->getHeaderLine('X-Request-Id');
```

Leave its body alone: `events()` reads the stream from it.
