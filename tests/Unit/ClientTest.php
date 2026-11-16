<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HosmelQ\SSE\Client;
use HosmelQ\SSE\EventSource;
use HosmelQ\SSE\ServerSentEvent;
use HosmelQ\SSE\SSEConnectionException;
use HosmelQ\SSE\SSEProtocolException;

it('accepts an HTTP client interface and only forces SSE request options', function (): void {
    $response = new Response(200, ['Content-Type' => 'text/event-stream'], "data: test\n\n");
    $httpClient = test()->createMock(ClientInterface::class);

    $httpClient
        ->expects(test()->once())
        ->method('request')
        ->with('PATCH', 'https://example.com/sse', [
            'headers' => [
                'X-Token' => 'token',
                'Accept' => 'text/event-stream',
                'Cache-Control' => 'no-store',
            ],
            'query' => ['channel' => 'news'],
            'json' => ['subscribe' => true],
            'stream' => true,
            'http_errors' => false,
        ])
        ->willReturn($response);

    $source = new Client($httpClient)->connect('PATCH', 'https://example.com/sse', [
        'headers' => [
            'accept' => 'application/json',
            'ACCEPT' => 'text/plain',
            'cAcHe-CoNtRoL' => 'max-age=60',
            'X-Token' => 'token',
        ],
        'query' => ['channel' => 'news'],
        'json' => ['subscribe' => true],
        'stream' => false,
        'http_errors' => true,
    ]);

    expect($source->getResponse())->toBe($response);
});

it('passes headers and timeout options through both shortcuts', function (string $method): void {
    $history = [];
    $stack = HandlerStack::create(
        new MockHandler([new Response(200, ['Content-Type' => 'text/event-stream'])]),
    );

    $stack->push(Middleware::history($history));

    $client = new Client(new HttpClient(['handler' => $stack]));
    $source = $client->{$method}('https://example.com/sse', [
        'headers' => [
            'accept' => 'application/json',
            'cache-control' => 'public',
            'Authorization' => 'Bearer token',
        ],
        'timeout' => 23,
        'read_timeout' => 4,
        'connect_timeout' => 2,
    ]);
    $request = $history[0]['request'];
    $options = $history[0]['options'];

    expect($source)->toBeInstanceOf(EventSource::class)
        ->and($request->getMethod())->toBe(mb_strtoupper($method))
        ->and($request->getHeader('Accept'))->toBe(['text/event-stream'])
        ->and($request->getHeader('Cache-Control'))->toBe(['no-store'])
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer token')
        ->and([$options['timeout'], $options['read_timeout'], $options['connect_timeout']])->toBe([
            23,
            4,
            2,
        ]);
})->with(['get', 'post']);

it('resumes after the event ID sent in the Last-Event-ID header', function (
    array $headers,
    string $sent,
): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(
            200,
            ['Content-Type' => 'text/event-stream'],
            "retry: 1000\n\ndata: first\n\nid: 8\ndata: second\n\n",
        ),
    ]));

    $stack->push(Middleware::history($history));

    $source = new Client(new HttpClient(['handler' => $stack]))->get('https://example.com/sse', [
        'headers' => $headers,
    ]);
    $ids = array_map(
        fn (ServerSentEvent $event): string => $event->id,
        iterator_to_array($source->events()),
    );

    expect($history[0]['request']->getHeaderLine('Last-Event-ID'))->toBe($sent)
        ->and($ids)->toBe([$sent, '8'])
        ->and($source->getLastEventId())->toBe('8')
        ->and($source->getRetry())->toBe(1_000);
})->with([
    'string' => [['Last-Event-ID' => '7'], '7'],
    'lowercase name' => [['last-event-id' => '7'], '7'],
    'list' => [['Last-Event-ID' => ['7']], '7'],
    'surrounding whitespace' => [['Last-Event-ID' => " 7\t"], '7'],
    'bytes that are not UTF-8' => [['Last-Event-ID' => " \xFF7\t"], "\xFF7"],
]);

it('starts from an integer Last-Event-ID header', function (): void {
    $httpClient = test()->createMock(ClientInterface::class);

    $httpClient->method('request')->willReturn(
        new Response(200, ['Content-Type' => 'text/event-stream'], "data: next\n\n"),
    );

    $source = new Client($httpClient)->get('https://example.com/sse', [
        'headers' => ['Last-Event-ID' => 7],
    ]);

    expect(iterator_to_array($source->events())[0]->id)->toBe('7');
});

it('preserves client-level timeout configuration', function (): void {
    $history = [];
    $stack = HandlerStack::create(
        new MockHandler([new Response(200, ['Content-Type' => 'text/event-stream'])]),
    );

    $stack->push(Middleware::history($history));

    $client = new Client(new HttpClient([
        'handler' => $stack,
        'timeout' => 12,
        'read_timeout' => 0.25,
        'connect_timeout' => 3,
    ]));

    $client->get('https://example.com/sse');

    $options = $history[0]['options'];

    expect([$options['timeout'], $options['read_timeout'], $options['connect_timeout']])->toBe([
        12,
        0.25,
        3,
    ]);
});

it('wraps transfer failures without exposing URL credentials or parameters', function (
    TransferException $failure,
): void {
    $client = new Client(new HttpClient(['handler' => new MockHandler([$failure])]));

    try {
        $client->get('https://user:password@example.com/sse?token=secret#private');
    } catch (SSEConnectionException $sseConnectionException) {
        expect($sseConnectionException->getPrevious())->toBe($failure)
            ->and($sseConnectionException->getMessage())->toBe(
                'Failed to connect to SSE endpoint: https://example.com/sse',
            );

        return;
    }

    test()->fail('Expected a connection exception.');
})->with([
    fn (): RequestException
        => new RequestException('Transfer failed.', new Request('GET', 'https://example.com/sse')),
    fn (): ConnectException
        => new ConnectException(
            'Connection failed.',
            new Request('GET', 'https://example.com/sse'),
        ),
]);

it('does not wrap invalid request options', function (): void {
    $client = new Client(new HttpClient(['handler' => new MockHandler([])]));

    expect(fn (): EventSource => $client->get('https://example.com/sse', ['body' => ['invalid']]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects invalid header options instead of silently discarding them', function (): void {
    $client = new Client(new HttpClient(['handler' => new MockHandler([])]));

    expect(fn (): EventSource => $client->get('https://example.com/sse', ['headers' => 'invalid']))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes HTTP errors as protocol failures with the original response', function (): void {
    $response = new Response(
        401,
        ['Content-Type' => 'application/json'],
        '{"error":"invalid token"}',
    );
    $client = new Client(
        new HttpClient(['handler' => HandlerStack::create(new MockHandler([$response]))]),
    );

    try {
        $client->get('https://example.com/sse', ['http_errors' => true]);
    } catch (SSEProtocolException $sseProtocolException) {
        expect($sseProtocolException->getMessage())->toBe('Expected status code 200, got 401.')
            ->and($sseProtocolException->getResponse())->toBe($response)
            ->and($response->getBody()->tell())->toBe(0)
            ->and($response->getBody()->getContents())->toBe('{"error":"invalid token"}');

        return;
    }

    test()->fail('Expected a protocol exception.');
});
