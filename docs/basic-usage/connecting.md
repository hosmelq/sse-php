---
title: "Connecting"
description: "Open an event stream with a GET or POST request, pass Guzzle request options, and reuse your own Guzzle client."
weight: 1
---

`Client` sends the request and returns an `EventSource` as soon as the response headers arrive. The connection stays open while you [read the events](reading-events).

```php
use HosmelQ\SSE\Client;

$client = new Client();

$source = $client->get('https://example.com/events');
```

## POST requests

Many APIs stream their response to a POST request. Send the body with any Guzzle request option, such as `json`:

```php
$source = $client->post('https://api.example.com/v1/responses', [
    'json' => ['input' => 'Hello', 'stream' => true],
]);
```

For other HTTP methods, pass the method to `connect()`:

```php
$source = $client->connect('PUT', 'https://api.example.com/v1/jobs/42/watch');
```

## Request options

The last argument accepts [Guzzle request options](https://docs.guzzlephp.org/en/stable/request-options.html), such as `headers`, `query`, and timeouts:

```php
$source = $client->get('https://api.example.com/v1/events', [
    'headers' => ['Authorization' => 'Bearer '.$token],
    'query' => ['channel' => 'orders'],
    'read_timeout' => 120,
]);
```

The client always sets these options, replacing the values you pass:

- `stream` is `true`, so you receive each event when it arrives instead of after the response ends.
- `http_errors` is `false`, so error responses reach the [response checks](handling-errors#error-responses) instead of throwing Guzzle exceptions.
- The `Accept` header is `text/event-stream` and the `Cache-Control` header is `no-store`.

It leaves the timeout options alone. [Timeouts](../advanced-usage/timeouts) explains which ones to set.

## Your own Guzzle client

Pass a Guzzle client to reuse its configuration, such as a base URI, default headers, middleware, or timeouts:

```php
use GuzzleHttp\Client as HttpClient;
use HosmelQ\SSE\Client;

$client = new Client(new HttpClient([
    'base_uri' => 'https://api.example.com/v1/',
    'headers' => ['Authorization' => 'Bearer '.$token],
    'read_timeout' => 120,
]));

$source = $client->get('events');
```

Streaming needs a handler that supports Guzzle's `stream` option. Guzzle's default handler stack streams through PHP's HTTP stream wrapper when the `allow_url_fopen` setting is on. With that setting off, or with a handler stack built on cURL alone, Guzzle downloads the whole response before you get the first event.

With Guzzle 7, don't ask for a compressed response with an `Accept-Encoding` header: Guzzle 7 decompresses it in large blocks, so the events only reach you when the stream ends. Guzzle 8 streams compressed responses as they arrive.
