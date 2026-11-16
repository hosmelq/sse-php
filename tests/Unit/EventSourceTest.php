<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use HosmelQ\SSE\EventSource;
use HosmelQ\SSE\ServerSentEvent;
use HosmelQ\SSE\SSEConnectionException;
use HosmelQ\SSE\SSEException;
use HosmelQ\SSE\SSEProtocolException;
use HosmelQ\SSE\Tests\Support\ChunkStream;

it('parses line endings regardless of chunk boundaries', function (
    string $content,
    int $chunkSize,
): void {
    $body = new ChunkStream(mb_str_split($content, $chunkSize, '8bit'));
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));
    $events = iterator_to_array($source->events());

    expect(array_map(fn (ServerSentEvent $event): array => $event->jsonSerialize(), $events))
        ->toBe([
            ['data' => 'one', 'event' => 'message', 'id' => '42', 'retry' => null],
            ['data' => 'two', 'event' => 'message', 'id' => '42', 'retry' => null],
        ])
        ->and($source->getLastEventId())
        ->toBe('42')
        ->and($body->closed)
        ->toBeTrue();
})->with([
    'LF' => "id:42\ndata: one\n\ndata: two\n\n",
    'CR' => "id:42\rdata: one\r\rdata: two\r\r",
    'CRLF' => "id:42\r\ndata: one\r\n\r\ndata: two\r\n\r\n",
    'mixed' => "id:42\rdata: one\n\rdata: two\r\n\n",
])->with([1, 2, 3, 7, 8_192]);

it('strips exactly one leading BOM even when split across chunks', function (int $chunkSize): void {
    $content = "\xEF\xBB\xBFdata: first\n\ndata: \xEF\xBB\xBFsecond\n\n";
    $body = new ChunkStream(mb_str_split($content, $chunkSize, '8bit'));
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    expect(
        array_map(
            fn (ServerSentEvent $event): string => $event->data,
            iterator_to_array($source->events()),
        ),
    )->toBe(['first', "\xEF\xBB\xBFsecond"]);
})->with([1, 2, 3, 8_192]);

it('preserves partial BOM bytes when they are not a BOM', function (string $prefix): void {
    $content = 'data: '.$prefix."\n\n";
    $body = new ChunkStream(mb_str_split($content, 1, '8bit'));
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    expect(iterator_to_array($source->events())[0]->data)->toBe($prefix);
})->with(["\xEF", "\xEF\xBB", "\xEF\xBB!", "\xFF"]);

it('does not strip another BOM at the start', function (): void {
    $body = new ChunkStream(["\xEF\xBB\xBF\xEF\xBB\xBFdata: ignored\n\ndata: kept\n\n"]);
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    expect(
        array_map(
            fn (ServerSentEvent $event): string => $event->data,
            iterator_to_array($source->events()),
        ),
    )->toBe(['kept']);
});

it('discards unterminated lines and incomplete events at EOF', function (string $tail): void {
    $source = new EventSource(
        new Response(200, ['Content-Type' => 'text/event-stream'], "data: complete\n\n".$tail),
    );

    expect(
        array_map(
            fn (ServerSentEvent $event): string => $event->data,
            iterator_to_array($source->events()),
        ),
    )->toBe(['complete']);
})->with([
    'data: no newline',
    "data: no blank line\n",
    "data: incomplete\nid: pending",
    ': comment',
]);

it('preserves multibyte and invalid bytes in every event field', function (int $chunkSize): void {
    $data = "  café 🐈\xFF\0\xC3 ";
    $id = "é\xFF";
    $event = "更新\xC3";
    $body = new ChunkStream(
        mb_str_split('data: '.$data."\nid: ".$id."\nevent: ".$event."\n\n", $chunkSize, '8bit'),
    );
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));
    $events = iterator_to_array($source->events());

    expect($events)->toHaveCount(1)->and($events[0]->jsonSerialize())->toBe([
        'data' => $data,
        'event' => $event,
        'id' => $id,
        'retry' => null,
    ])->and($source->getLastEventId())->toBe($id);
})->with([1, 2, 7, 8_192]);

it('continues after empty reads before EOF', function (): void {
    $body = new ChunkStream(['', 'data: hel', '', "lo\n", '', "\n"]);
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    expect(iterator_to_array($source->events())[0]->data)->toBe('hello');
});

it('yields a completed event before reading more data', function (): void {
    $body = new ChunkStream(["data: first\r\r", new RuntimeException('No next chunk yet.')]);
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    foreach ($source->events() as $event) {
        expect($event->data)->toBe('first')->and($body->reads)->toHaveCount(1);

        break;
    }

    expect($body->closed)->toBeTrue()->and($body->isReadable())->toBeFalse();
});

it('yields events from a chunked PHP socket stream without waiting to fill a buffer', function (): void {
    [$server, $client] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    stream_filter_append($client, 'dechunk', STREAM_FILTER_READ);
    stream_set_timeout($client, 5);
    fwrite($server, "b\r\ndata: one\n\n\r\n");

    $source = new EventSource(
        new Response(200, ['Content-Type' => 'text/event-stream'], new Stream($client)),
    );
    $start = microtime(true);
    $event = $source->events()->current();

    expect($event?->data)->toBe('one')->and(microtime(true) - $start)->toBeLessThan(1.0);

    fclose($server);
});

it('closes the body when the source is destroyed without iteration', function (): void {
    $body = new ChunkStream(["data: unused\n\n"]);
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    unset($source);

    expect($body->closed)->toBeTrue();
});

it('rejects a second iteration after consumption', function (): void {
    $source = new EventSource(
        new Response(200, ['Content-Type' => 'text/event-stream'], "data: once\n\n"),
    );

    iterator_to_array($source->events());

    expect(fn (): array => iterator_to_array($source->events()))
        ->toThrow(SSEException::class, 'The event stream has already been consumed or closed.');
});

it('rejects iteration after explicitly closing the source', function (): void {
    $body = new ChunkStream(["data: unused\n\n"]);
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    $source->close();
    $source->close();

    expect($body->closed)->toBeTrue()
        ->and(fn (): array => iterator_to_array($source->events()))
        ->toThrow(SSEException::class, 'The event stream has already been consumed or closed.');
});

it('wraps body read failures and preserves the exception', function (): void {
    $failure = new RuntimeException('The connection ended unexpectedly.');
    $body = new ChunkStream(["data: first\n\n", $failure]);
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));
    $events = [];

    try {
        foreach ($source->events() as $event) {
            $events[] = $event->data;
        }
    } catch (SSEConnectionException $sseConnectionException) {
        expect($sseConnectionException->getPrevious())->toBe($failure)
            ->and($events)->toBe(['first'])->and($body->closed)->toBeTrue();

        return;
    }

    test()->fail('Expected a connection exception.');
});

it('returns the original response without reading its body', function (): void {
    $body = new ChunkStream(["data: unread\n\n"]);
    $response = new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    $source = new EventSource($response);

    expect($source->getResponse())->toBe($response)->and($body->reads)->toBeEmpty();
});

it('accepts the media type case-insensitively and before parameters', function (
    string $contentType,
): void {
    $response = new Response(200, ['Content-Type' => $contentType], "data: valid\n\n");

    expect(iterator_to_array(new EventSource($response)->events())[0]->data)->toBe('valid');
})->with([
    'text/event-stream',
    'Text/Event-Stream; charset=utf-8',
    ' text/event-stream ; charset=UTF-8',
]);

it('exposes rejected responses with an unread body', function (
    int $status,
    string $contentType,
    string $message,
): void {
    $response = new Response(
        $status,
        $contentType === '' ? [] : ['Content-Type' => $contentType],
        '{"error":"denied"}',
    );

    try {
        new EventSource($response);
    } catch (SSEProtocolException $sseProtocolException) {
        expect($sseProtocolException->getMessage())->toBe($message)
            ->and($sseProtocolException->getResponse())->toBe($response)
            ->and($response->getBody()->tell())->toBe(0)
            ->and($response->getBody()->isReadable())->toBeTrue()
            ->and($response->getBody()->getContents())->toBe('{"error":"denied"}');

        return;
    }

    test()->fail('Expected a protocol exception.');
})->with([
    [401, 'application/json', 'Expected status code 200, got 401.'],
    [204, 'text/event-stream', 'Expected status code 200, got 204.'],
    [201, 'text/event-stream', 'Expected status code 200, got 201.'],
    [500, 'application/json', 'Expected status code 200, got 500.'],
    [200, 'application/json', "Expected Content-Type 'text/event-stream', got 'application/json'."],
    [200, '', "Expected Content-Type 'text/event-stream', got ''."],
    [
        200,
        'application/json; note=text/event-stream',
        "Expected Content-Type 'text/event-stream', got 'application/json; note=text/event-stream'.",
    ],
]);

it('preserves a data line spanning multiple read buffers', function (): void {
    $data = str_repeat("é🐈\xFF", 3_000);
    $body = new ChunkStream(mb_str_split('data: '.$data."\n\n", 8_192, '8bit'));
    $source = new EventSource(new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    expect(iterator_to_array($source->events())[0]->data)->toBe($data);
});
