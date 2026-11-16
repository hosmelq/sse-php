---
title: "Handling Errors"
description: "Catch connection failures, error responses, and streams that fail mid-way, and read the response a server sent instead of events."
weight: 3
---

Connection and stream failures throw exceptions that extend `HosmelQ\SSE\SSEException`:

- `SSEConnectionException` when the request fails before a response arrives, such as a DNS, connection, or TLS error, or when reading the stream fails, such as an idle timeout.
- `SSEProtocolException` when the server answers with something other than an event stream: a status other than `200`, or a `Content-Type` other than `text/event-stream`.

Invalid request options, such as `headers` that are not an array, throw `InvalidArgumentException` instead, so a mistake in your code does not look like a connection failure.

```php
use HosmelQ\SSE\Client;
use HosmelQ\SSE\SSEConnectionException;
use HosmelQ\SSE\SSEProtocolException;

try {
    $source = new Client()->get('https://api.example.com/v1/events');

    foreach ($source->events() as $event) {
        // ...
    }
} catch (SSEProtocolException $e) {
    $response = $e->getResponse();

    $response->getStatusCode(); // 401
    (string) $response->getBody(); // {"error":"invalid token"}
} catch (SSEConnectionException $e) {
    $e->getPrevious(); // The exception that caused it
}
```

## Error responses

The client turns off Guzzle's `http_errors` option, so a `401` or `500` response throws `SSEProtocolException` instead of a Guzzle exception. `getResponse()` returns that response with its body unread, so you can read the error the server sent.

The status must be exactly `200`, as browsers require. This includes `204 No Content`, which servers send to tell clients to stop reconnecting.

## Streams that fail mid-way

When reading the stream fails, the `events()` loop throws `SSEConnectionException`. A connection that drops does not always fail the read: depending on the Guzzle version and how the connection ends, the loop can also end as if the server had closed the stream. When a stream should stay open, treat the end of the loop as a disconnect too. [Reconnecting](../advanced-usage/reconnecting) shows how to resume.

## JSON data

`json()` throws `JsonException` when the data is not valid JSON. It does not extend `SSEException`.
