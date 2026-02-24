<?php

use Codenzia\BrowserConsole\Support\ConsoleDebug;

it('console helper function exists', function () {
    expect(function_exists('console'))->toBeTrue();
});

it('console helper returns ConsoleDebug instance', function () {
    $result = console('test');

    expect($result)->toBeInstanceOf(ConsoleDebug::class);
});

it('console helper passes values to ConsoleDebug', function () {
    $logPath = storage_path('logs/console-debug.log');

    if (file_exists($logPath)) {
        unlink($logPath);
    }

    $debug = console('hello', 42);
    unset($debug);

    $entry = json_decode(trim(file_get_contents($logPath)), true);

    expect($entry['values'])->toHaveCount(2)
        ->and($entry['values'][0]['value'])->toBe('hello')
        ->and($entry['values'][1]['value'])->toBe(42);

    // Clean up
    unlink($logPath);
});

it('console helper supports fluent chaining', function () {
    $logPath = storage_path('logs/console-debug.log');

    if (file_exists($logPath)) {
        unlink($logPath);
    }

    $debug = console('data')->label('Test')->blue();
    unset($debug);

    $entry = json_decode(trim(file_get_contents($logPath)), true);

    expect($entry['label'])->toBe('Test')
        ->and($entry['color'])->toBe('blue');

    // Clean up
    unlink($logPath);
});
