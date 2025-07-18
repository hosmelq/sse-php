<?php

declare(strict_types=1);

use HosmelQ\SSE\Decoder;

it('parses basic event data', function (): void {
    $decoder = new Decoder();

    $decoder->decode('data: Hello World');

    $event = $decoder->decode('');

    expect($event)
        ->data->toBe('Hello World')
        ->event->toBe('message');
});

it('ignores comment lines', function (): void {
    $decoder = new Decoder();

    $result = $decoder->decode(': This is a comment');

    expect($result)->toBeNull();
});

it('accumulates multiple data lines', function (): void {
    $decoder = new Decoder();

    $decoder->decode('data: line1');
    $decoder->decode('data: line2');
    $decoder->decode('data: line3');

    $event = $decoder->decode('');

    expect($event->data)->toBe("line1\nline2\nline3");
});

it('parses event and id fields', function (): void {
    $decoder = new Decoder();

    $decoder->decode('event: user_update');
    $decoder->decode('id: 123');
    $decoder->decode('data: test');

    $event = $decoder->decode('');

    expect($event)
        ->event->toBe('user_update')
        ->id->toBe('123');
});

it('ignores id with null bytes', function (): void {
    $decoder = new Decoder();

    $decoder->decode('id: 123 456');
    $decoder->decode('data: test');

    $event = $decoder->decode('');

    expect($event->id)->toBe('');
});

it('parses valid retry field', function (): void {
    $decoder = new Decoder();

    $decoder->decode('retry: 5000');
    $decoder->decode('data: test');

    $event = $decoder->decode('');

    expect($event->retry)->toBe(5000);
});

it('ignores invalid retry values', function (): void {
    $decoder = new Decoder();

    $decoder->decode('retry: invalid');
    $decoder->decode('data: test');

    $event = $decoder->decode('');

    expect($event->retry)->toBeNull();
});

it('handles field without colon', function (): void {
    $decoder = new Decoder();

    $decoder->decode('data');

    $event = $decoder->decode('');

    expect($event->data)->toBe('');
});

it('preserves last event id across events', function (): void {
    $decoder = new Decoder();

    $decoder->decode('id: 123');
    $decoder->decode('data: first');
    $decoder->decode('');

    $decoder->decode('data: second');

    $event = $decoder->decode('');

    expect($event->id)->toBe('123');
});

it('returns null for empty event', function (): void {
    $decoder = new Decoder();

    $event = $decoder->decode('');

    expect($event)->toBeNull();
});
