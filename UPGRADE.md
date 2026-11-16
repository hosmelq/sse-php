# Upgrade Guide

## Upgrading to 1.0 from 0.1

### Runtime requirements

PHP 8.4 is now the minimum supported version. Guzzle 8.5 or newer is supported alongside Guzzle
7.15.2 or newer, and guzzlehttp/psr7 must be 2.13 or newer, or 3.4 or newer. Older Guzzle and
PSR-7 releases have security advisories.

### Error responses

The client no longer lets Guzzle throw for error responses. A response whose status is not 200,
including other 2xx statuses, now throws `SSEProtocolException`, which exposes the response through
`getResponse()`:

```php
use HosmelQ\SSE\SSEProtocolException;

try {
    $source = $client->get($url);
} catch (SSEProtocolException $e) {
    $status = $e->getResponse()->getStatusCode();
}
```

Before, a `401` response threw `SSEConnectionException` with Guzzle's `ClientException` as its
previous exception. Update handlers that read error responses through `getPrevious()`.

### Read failures

Failures while reading the stream, such as idle timeouts, now throw `SSEConnectionException`
instead of the `RuntimeException` that Guzzle's streams throw. Catch `SSEException` to handle every
failure.

### Timeouts

The client no longer sets `timeout` and `read_timeout` on each request. It used to force a
10-second `read_timeout` and a `timeout` of `0`, which replaced the configuration of the Guzzle
client you passed. Guzzle's defaults now apply unless you set the options, so set `read_timeout`
to the longest pause you expect between events.

### Guzzle client type

`Client::__construct()` accepts any `GuzzleHttp\ClientInterface` instead of only
`GuzzleHttp\Client`. Code that passes a `GuzzleHttp\Client` keeps working.

### Events without data

Like browsers, the stream now yields only events with at least one `data` line. Blocks that only
set `id`, `event`, or `retry` no longer produce events with empty data, and neither do blank lines
or comments after an event ID was set. Read the values those blocks set from the event source:

```php
$source->getLastEventId();
$source->getRetry(); // New: the last reconnection time in milliseconds, or null
```

### Last event ID

An `id` field becomes the last event ID when its event ends, not when the line arrives, so an
event cut off by a dropped connection no longer moves it. When you pass a `Last-Event-ID`
header with the request, the stream starts with that ID, and `EventSource::__construct()` accepts
it as a second argument.

### Retry field

The `retry` field must contain only ASCII digits, as the standard requires. Values such as `1.5`,
`1e3`, or `+5` are now ignored instead of being truncated to an integer.

### Internal classes

`Decoder` is now internal. Parse responses with `EventSource`.
