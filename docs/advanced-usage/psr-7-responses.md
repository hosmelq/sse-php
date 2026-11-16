---
title: "PSR-7 Responses"
description: "Read the events of a PSR-7 response you already have, such as one from your own Guzzle call or an SDK."
weight: 3
---

`EventSource` reads the events of any PSR-7 response, so you can parse a stream you requested another way, such as with your own Guzzle call or an SDK built on PSR-7:

```php
use GuzzleHttp\Client as HttpClient;
use HosmelQ\SSE\EventSource;

$response = new HttpClient()->get('https://example.com/events', [
    'headers' => ['Accept' => 'text/event-stream'],
    'http_errors' => false,
    'read_timeout' => 120,
    'stream' => true,
]);

$source = new EventSource($response);

foreach ($source->events() as $event) {
    // ...
}
```

The constructor checks the response the same way `Client` does, and throws `SSEProtocolException` when the status is not `200` or the `Content-Type` is not `text/event-stream`.

When the request sent a `Last-Event-ID` header to resume a stream, pass the same ID as the second argument, so events keep it until the server sends a new one:

```php
$source = new EventSource($response, $lastEventId);
```

Request the response with streaming turned on. Otherwise the HTTP client downloads the whole body before you get the response, and every event arrives at once when the stream ends.
