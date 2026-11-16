<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use HosmelQ\SSE\EventSource;
use HosmelQ\SSE\ServerSentEvent;

/**
 * Tests based on WHATWG SSE specification examples.
 *
 * @see https://html.spec.whatwg.org/multipage/server-sent-events.html#event-stream-interpretation
 */
function createResponse(string $content): Response
{
    $stream = fopen('php://memory', 'r+');

    fwrite($stream, $content);
    rewind($stream);

    return new Response(
        200,
        ['content-type' => 'text/event-stream'],
        new Stream($stream),
    );
}

it('whatwg example1', function (): void {
    $content = "data: YH00\n".
              "data: +2\n".
              "data: 10\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect($events)
        ->toHaveCount(1)
        ->{0}->event->toBe('message')
        ->{0}->data->toBe("YH00\n+2\n10")
        ->{0}->id->toBe('')
        ->{0}->retry->toBeNull();
});

it('whatwg example2', function (): void {
    $content = ": test stream\n".
              "\n".
              "data: first event\n".
              "id: 1\n".
              "\n".
              "data: second event\n".
              "id\n".
              "\n".
              "data:  third event\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect(array_map(fn (ServerSentEvent $event): array => $event->jsonSerialize(), $events))->toBe(
        [
            ['data' => 'first event', 'event' => 'message', 'id' => '1', 'retry' => null],
            ['data' => 'second event', 'event' => 'message', 'id' => '', 'retry' => null],
            ['data' => ' third event', 'event' => 'message', 'id' => '', 'retry' => null],
        ],
    );
});

it('whatwg example3', function (): void {
    $content = "data\n".
              "\n".
              "data\n".
              "data\n".
              "\n".
              "data:\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect(array_map(fn (ServerSentEvent $event): array => $event->jsonSerialize(), $events))->toBe(
        [
            ['data' => '', 'event' => 'message', 'id' => '', 'retry' => null],
            ['data' => "\n", 'event' => 'message', 'id' => '', 'retry' => null],
        ],
    );
});

it('whatwg example4', function (): void {
    $content = "data:test\n".
              "\n".
              "data: test\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect(array_map(fn (ServerSentEvent $event): array => $event->jsonSerialize(), $events))->toBe(
        [
            ['data' => 'test', 'event' => 'message', 'id' => '', 'retry' => null],
            ['data' => 'test', 'event' => 'message', 'id' => '', 'retry' => null],
        ],
    );
});

it('event field', function (): void {
    $content = "event: logline\n".
              "data: New user connected\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect($events)
        ->toHaveCount(1)
        ->{0}->data->toBe('New user connected')
        ->{0}->event->toBe('logline')
        ->{0}->id->toBe('')
        ->{0}->retry->toBeNull();
});

it('id with null byte', function (): void {
    $content = "data: test\n".
              "id: 123\x00\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect($events)
        ->toHaveCount(1)
        ->{0}->data->toBe('test')
        ->{0}->event->toBe('message')
        ->{0}->id->toBe('')
        ->{0}->retry->toBeNull();
});

it('retry field', function (): void {
    $content = "retry: 10000\n".
              "\n";
    $source = new EventSource(createResponse($content));

    expect(iterator_to_array($source->events()))->toBeEmpty()
        ->and($source->getRetry())->toBe(10_000);
});

it('invalid retry field', function (): void {
    $content = "retry: 1667a\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect($events)->toBeEmpty();
});

it('unknown field', function (): void {
    $content = "something: ignore\n".
              "\n";
    $response = createResponse($content);
    $events = iterator_to_array(new EventSource($response)->events());

    expect($events)->toBeEmpty();
});
