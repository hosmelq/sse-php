---
title: "Introduction"
description: "PHP SSE connects to Server-Sent Events endpoints with Guzzle and yields each event as the server sends it."
weight: 1
---

PHP SSE connects to Server-Sent Events (SSE) endpoints with Guzzle and yields each event as the server sends it. It parses streams the way the WHATWG HTML standard describes, including every line ending, comments, event IDs, and reconnection times, so it works with notification feeds and with APIs that stream their responses.

## Quick start

After [installing the package](installation), connect to an endpoint and iterate its events:

```php
use HosmelQ\SSE\Client;

$source = new Client()->get('https://example.com/events');

foreach ($source->events() as $event) {
    echo $event->event.': '.$event->data.PHP_EOL;
}
```

The loop ends when the server closes the stream.

## Next steps

- [Connecting](basic-usage/connecting) covers POST requests, request options, and your own Guzzle client.
- [Reading Events](basic-usage/reading-events) describes the fields of each event and how to decode JSON data.
- [Handling Errors](basic-usage/handling-errors) explains which exceptions to catch.
- [Reconnecting](advanced-usage/reconnecting) resumes a stream after it drops.

## Credits

PHP SSE is based on [httpx-sse](https://github.com/florimondmanca/httpx-sse).
