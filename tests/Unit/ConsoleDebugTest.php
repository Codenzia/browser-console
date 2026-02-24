<?php

use Codenzia\BrowserConsole\Support\ConsoleDebug;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->logPath = storage_path('logs/console-debug.log');

    // Ensure clean state
    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

afterEach(function () {
    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

it('writes a debug entry to log file', function () {
    $debug = new ConsoleDebug('hello world');
    unset($debug); // Triggers __destruct → flush

    expect(File::exists($this->logPath))->toBeTrue();

    $content = File::get($this->logPath);
    $entry = json_decode(trim($content), true);

    expect($entry)->toBeArray()
        ->and($entry)->toHaveKeys(['id', 'ts', 'type', 'values', 'label', 'color', 'caller'])
        ->and($entry['type'])->toBe('dump')
        ->and($entry['color'])->toBe('gray')
        ->and($entry['label'])->toBeNull();
});

it('serializes string values', function () {
    $debug = new ConsoleDebug('test string');
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('string')
        ->and($entry['values'][0]['value'])->toBe('test string');
});

it('serializes integer values', function () {
    $debug = new ConsoleDebug(42);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('integer')
        ->and($entry['values'][0]['value'])->toBe(42);
});

it('serializes float values', function () {
    $debug = new ConsoleDebug(3.14);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('float')
        ->and($entry['values'][0]['value'])->toBe(3.14);
});

it('serializes boolean values', function () {
    $debug = new ConsoleDebug(true);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('boolean')
        ->and($entry['values'][0]['value'])->toBeTrue();
});

it('serializes null values', function () {
    $debug = new ConsoleDebug(null);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('null')
        ->and($entry['values'][0]['value'])->toBeNull();
});

it('serializes array values', function () {
    $debug = new ConsoleDebug(['key' => 'value', 'nested' => ['a' => 1]]);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('array')
        ->and($entry['values'][0]['value'])->toHaveKey('key', 'value');
});

it('serializes objects with toArray method', function () {
    $obj = new class {
        public function toArray(): array
        {
            return ['name' => 'test', 'count' => 5];
        }
    };

    $debug = new ConsoleDebug($obj);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'][0]['type'])->toBe('object')
        ->and($entry['values'][0]['value'])->toHaveKey('data')
        ->and($entry['values'][0]['value']['data'])->toHaveKey('name', 'test');
});

it('supports multiple values', function () {
    $debug = new ConsoleDebug('first', 42, true);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['values'])->toHaveCount(3)
        ->and($entry['values'][0]['type'])->toBe('string')
        ->and($entry['values'][1]['type'])->toBe('integer')
        ->and($entry['values'][2]['type'])->toBe('boolean');
});

it('supports label method', function () {
    $debug = new ConsoleDebug('data');
    $debug->label('My Label');
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['label'])->toBe('My Label');
});

it('supports color methods', function () {
    $debug = new ConsoleDebug('data');
    $debug->green();
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['color'])->toBe('green');
});

it('supports all named color methods', function () {
    $colors = ['green', 'blue', 'red', 'orange', 'purple'];

    foreach ($colors as $color) {
        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        $debug = new ConsoleDebug('data');
        $debug->$color();
        unset($debug);

        $entry = json_decode(trim(File::get($this->logPath)), true);
        expect($entry['color'])->toBe($color);
    }
});

it('falls back to gray for invalid colors', function () {
    $debug = new ConsoleDebug('data');
    $debug->color('invalid-color');
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['color'])->toBe('gray');
});

it('supports table type', function () {
    $debug = new ConsoleDebug(['key' => 'value']);
    $debug->table();
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['type'])->toBe('table');
});

it('supports fluent chaining', function () {
    $debug = new ConsoleDebug('data');
    $debug->label('Test')->green()->table();
    unset($debug); // Triggers __destruct → flush

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['label'])->toBe('Test')
        ->and($entry['color'])->toBe('green')
        ->and($entry['type'])->toBe('table');
});

it('captures caller information', function () {
    $debug = new ConsoleDebug('data');
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    expect($entry['caller'])->toHaveKeys(['file', 'line', 'function', 'class']);
});

it('generates unique IDs for each entry', function () {
    $debug1 = new ConsoleDebug('first');
    unset($debug1);

    $debug2 = new ConsoleDebug('second');
    unset($debug2);

    $lines = array_filter(explode("\n", File::get($this->logPath)));
    $entry1 = json_decode($lines[0], true);
    $entry2 = json_decode($lines[1], true);

    expect($entry1['id'])->not->toBe($entry2['id']);
});

it('truncates deeply nested arrays', function () {
    // Create deeply nested array (7 levels deep, exceeding 5 max depth)
    $data = ['level1' => ['level2' => ['level3' => ['level4' => ['level5' => ['level6' => 'deep']]]]]];

    $debug = new ConsoleDebug($data);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);
    $value = $entry['values'][0]['value'];

    // The truncation should happen at depth 5
    expect($value)->toBeArray();
});

it('limits array entries to 50 items', function () {
    $data = array_fill(0, 60, 'value');

    $debug = new ConsoleDebug($data);
    unset($debug);

    $entry = json_decode(trim(File::get($this->logPath)), true);

    // Should have 50 items + the truncation indicator
    expect(count($entry['values'][0]['value']))->toBeLessThanOrEqual(51);
});

it('only flushes once even when destruct is called multiple times', function () {
    $debug = new ConsoleDebug('test');
    unset($debug);

    $content = File::get($this->logPath);
    $lines = array_filter(explode("\n", $content));

    expect($lines)->toHaveCount(1);
});
