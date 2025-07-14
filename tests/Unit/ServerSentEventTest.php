<?php

declare(strict_types=1);

use HosmelQ\SSE\ServerSentEvent;

it('creates event with default values', function (): void {
    $event = new ServerSentEvent();

    expect($event)
        ->data->toBe('')
        ->event->toBe('message')
        ->id->toBe('')
        ->retry->toBeNull();
});

it('creates event with custom values', function (): void {
    $event = new ServerSentEvent(
        data: 'Hello World',
        event: 'user_update',
        id: '123',
        retry: 5000
    );

    expect($event)
        ->data->toBe('Hello World')
        ->event->toBe('user_update')
        ->id->toBe('123')
        ->retry->toBe(5000);
});

it('json decodes valid json data', function (): void {
    $jsonData = '{"name": "John", "age": 30}';
    $event = new ServerSentEvent(data: $jsonData);

    $decoded = $event->json();

    expect($decoded)->toBe(['name' => 'John', 'age' => 30]);
});

it('json throws exception for invalid json', function (): void {
    $event = new ServerSentEvent(data: 'invalid json {');

    $event->json();
})->throws(JsonException::class);
