# PHP SSE

Consume Server-Sent Events (SSE) with PHP.

## Features

- **WHATWG Compliant** - Implements the [Server-Sent Events specification](https://html.spec.whatwg.org/multipage/server-sent-events.html).
- **Memory Efficient** - Generator-based event iteration for low memory usage.

## Requirements

- PHP 8.2 or higher.

## Installation

Install via Composer:

```bash
composer require hosmelq/sse
```

## Quick Start

```php
<?php

use HosmelQ\SSE\Client;

$client = new Client();

$eventSource = $client->get('https://example.com/events');

foreach ($eventSource->events() as $event) {
    echo "Data: {$event->data}\n";
    echo "Event: {$event->event}\n";
    echo "ID: {$event->id}\n";
}
```

## Usage

### Creating a Client

The `Client` class is the main entry point for connecting to SSE endpoints:

```php
use HosmelQ\SSE\Client;
use GuzzleHttp\Client as HttpClient;

// Using a default HTTP client
$client = new Client();

// Using a custom HTTP client
$httpClient = new HttpClient(['timeout' => 30]);
$client = new Client($httpClient);
```

### Connecting to SSE Endpoints

The client supports both GET and POST methods:

```php
// GET request (most common for SSE)
$eventSource = $client->get('https://api.example.com/stream');

// POST request with data
$eventSource = $client->post('https://api.example.com/stream', [
    'json' => ['subscribe' => 'user-123']
]);

// Using generic connect method
$eventSource = $client->connect('GET', 'https://api.example.com/stream', [
    'read_timeout' => 30,
    'timeout' => 60,
]);
```

### Processing Events

```php
foreach ($eventSource->events() as $event) {
    echo $event->data;  // Event data
    echo $event->event; // Event type (default: "message")
    echo $event->id;    // Event ID (optional)
    echo $event->retry; // Retry interval (optional)
}
```

### Event Types and Data

Events follow the SSE specification with these fields:

```php
// Example event from server:
// data: {"message": "Hello World", "timestamp": 1640995200}
// event: notification
// id: 123
// retry: 5000

$event = /* ... received event ... */;

echo $event->data;  // '{"message": "Hello World", "timestamp": 1640995200}'
echo $event->event; // "notification"
echo $event->id;    // "123"
echo $event->retry; // 5000

// Parse JSON data
$notification = $event->json();

echo $notification['message']; // "Hello World"
```

### Configuration Options

```php
$eventSource = $client->get('https://api.example.com/stream', [
    'headers' => ['Authorization' => 'Bearer token'],
    'query' => ['channel' => 'notifications'],
    'read_timeout' => 30,
    'timeout' => 60,
]);
```

### Exception Handling

The library provides a clean exception hierarchy for different types of errors:

```
SSEException (base class)
├── SSEConnectionException (connection/transport errors)
└── SSEProtocolException (protocol violations)
```

#### Exception Types

**`SSEConnectionException`** - Thrown when connection or transport errors occur.

**`SSEProtocolException`** - Thrown when the server violates the SSE protocol.

**`SSEException`** - Base class that catches all SSE-related errors.

#### Error Handling Examples

```php
<?php

use HosmelQ\SSE\Client;
use HosmelQ\SSE\SSEException;
use HosmelQ\SSE\SSEConnectionException;
use HosmelQ\SSE\SSEProtocolException;

$client = new Client();

try {
    $eventSource = $client->get('https://api.example.com/stream');
    
    foreach ($eventSource->events() as $event) {
    }
} catch (SSEConnectionException $e) {
    echo "Connection failed: " . $e->getMessage();
} catch (SSEProtocolException $e) {
    echo "Protocol error: " . $e->getMessage();
} catch (SSEException $e) {
    echo "SSE Error: " . $e->getMessage();
}
```

### Last Event ID

Track the last received event ID for reconnection scenarios:

```php
$eventSource = $client->get('https://api.example.com/stream');

try {
    foreach ($eventSource->events() as $event) {
    }
} catch (Exception $e) {
    $lastEventId = $eventSource->getLastEventId();

    $eventSource = $client->get('https://api.example.com/stream', [
        'headers' => ['Last-Event-ID' => $lastEventId]
    ]);
}
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Credits

- [Hosmel Quintana](https://github.com/hosmelq)
- [All Contributors](../../contributors)

**Based on:**
- [httpx-sse](https://github.com/florimondmanca/httpx-sse)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
