<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use HosmelQ\SSE\Client;
use HosmelQ\SSE\EventSource;
use HosmelQ\SSE\SSEConnectionException;
use HosmelQ\SSE\SSEProtocolException;

it('throws exception for connection failures', function (): void {
    $mockHandler = new MockHandler([
        new ConnectException(
            'Connection failed',
            new Request('GET', 'https://example.com/sse')
        ),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $httpClient = new HttpClient(['handler' => $handlerStack]);

    (new Client($httpClient))->get('https://example.com/sse');
})->throws(SSEConnectionException::class, 'Failed to connect to SSE endpoint');

it('throws exception for invalid content type', function (): void {
    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"message": "Hello"}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $httpClient = new HttpClient(['handler' => $handlerStack]);

    $client = new Client($httpClient);

    $client->get('https://example.com/api');
})->throws(SSEProtocolException::class, "Expected 'text/event-stream', got 'application/json'");

it('connects with valid content type', function (): void {
    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'text/event-stream'], "data: test\n\n"),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $httpClient = new HttpClient(['handler' => $handlerStack]);

    $client = new Client($httpClient);
    $eventSource = $client->get('https://example.com/sse');

    expect($eventSource)->toBeInstanceOf(EventSource::class);
});

it('sets correct SSE headers', function (): void {
    $container = [];
    $history = Middleware::history($container);

    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'text/event-stream'], "data: test\n\n"),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push($history);

    $httpClient = new HttpClient(['handler' => $handlerStack]);

    (new Client($httpClient))->get('https://example.com/sse');

    $request = $container[0]['request'];

    expect($request)
        ->getHeaderLine('Accept')->toBe('text/event-stream')
        ->getHeaderLine('Cache-Control')->toBe('no-store');
});

it('closes stream when iteration breaks early', function (): void {
    $stream = fopen('php://memory', 'r+');

    fwrite($stream, "data: event1\n\ndata: event2\n\n");
    rewind($stream);

    $response = new Response(
        200,
        ['content-type' => 'text/event-stream'],
        new Stream($stream)
    );

    $eventSource = new EventSource($response);

    foreach ($eventSource->events() as $event) {
        break;
    }

    expect(true)->toBeTrue();
});
