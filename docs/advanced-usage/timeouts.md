---
title: "Timeouts"
description: "Choose how long a stream may stay silent before the read fails, and keep Guzzle's request timeout from cutting long streams."
weight: 1
---

An event stream stays open for as long as the server keeps sending, often far longer than a normal request. Guzzle's `read_timeout` option sets how many seconds the stream may stay silent before reading fails with `SSEConnectionException`. Guzzle 8 defaults it to 60 seconds. Guzzle 7 falls back to PHP's `default_socket_timeout` setting, also 60 seconds unless your configuration changes it.

Set it above the longest pause the server makes between events or keep-alive comments:

```php
$source = $client->get('https://api.example.com/v1/events', [
    'read_timeout' => 300,
]);
```

## The request timeout

Guzzle's `timeout` option limits the request as a whole. With Guzzle 8, it only covers the time until the response headers arrive, so it never cuts a stream that is already open. With Guzzle 7, it also limits each read of the stream when `read_timeout` is not set, so always set `read_timeout` for event streams.

The client does not change any timeout option, so the values you pass with a request or set on your [own Guzzle client](../basic-usage/connecting#your-own-guzzle-client) apply.
