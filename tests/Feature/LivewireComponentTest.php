<?php

use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);
});

it('mounts with default state', function () {
    Livewire::test(BrowserConsole::class)
        ->assertSet('mode', 'artisan')
        ->assertSet('command', '')
        ->assertSet('history', [])
        ->assertSet('logLines', 100)
        ->assertSet('logLevel', 'all')
        ->assertSet('refTab', 'commands')
        ->assertSet('commandSearch', '');
});

it('switches between modes', function () {
    $component = Livewire::test(BrowserConsole::class);

    $component->call('switchMode', 'shell')
        ->assertSet('mode', 'shell')
        ->assertSet('command', '')
        ->assertSet('commandSearch', '');

    $component->call('switchMode', 'logs')
        ->assertSet('mode', 'logs');

    $component->call('switchMode', 'debug')
        ->assertSet('mode', 'debug');

    $component->call('switchMode', 'artisan')
        ->assertSet('mode', 'artisan');
});

it('rejects invalid mode', function () {
    Livewire::test(BrowserConsole::class)
        ->call('switchMode', 'invalid')
        ->assertSet('mode', 'artisan'); // Should remain default
});

it('fills command from reference panel', function () {
    Livewire::test(BrowserConsole::class)
        ->call('fillCommand', 'migrate:status')
        ->assertSet('command', 'migrate:status');
});

it('clears history', function () {
    session(['console_authenticated' => true, 'console_last_activity' => time()]);

    Livewire::test(BrowserConsole::class)
        ->call('clearHistory')
        ->assertSet('history', []);
});

it('blocks command execution when not authenticated', function () {
    Livewire::test(BrowserConsole::class)
        ->set('command', 'route:list')
        ->call('runCommand')
        ->assertSet('history', []);
});

it('does not run empty commands', function () {
    session(['console_authenticated' => true, 'console_last_activity' => time()]);

    Livewire::test(BrowserConsole::class)
        ->set('command', '')
        ->call('runCommand')
        ->assertSet('history', []);
});

it('returns command reference groups', function () {
    $component = new BrowserConsole();
    $component->mount();
    $groups = $component->getCommandReference();

    expect($groups)->toBeArray()
        ->and(array_keys($groups))->each->toBeString();
});

it('returns shell command reference groups', function () {
    $component = new BrowserConsole();
    $groups = $component->getShellCommandReference();

    expect($groups)->toBeArray()
        ->and($groups)->toHaveKeys([__('Composer'), __('Git'), __('PHP Info'), __('System')]);
});

it('returns deployment guide', function () {
    $component = new BrowserConsole();
    $guide = $component->getDeploymentGuide();

    expect($guide)->toBeArray()
        ->and($guide)->toHaveKeys([__('Fresh Deployment'), __('Re-deployment / Update')]);
});

it('filters command reference by search term', function () {
    $component = new BrowserConsole();
    $component->mount();
    $component->commandSearch = 'migrate';
    $filtered = $component->getFilteredCommandReference();

    expect($filtered)->toBeArray();

    // All returned commands should match the search
    foreach ($filtered as $group => $commands) {
        foreach ($commands as $cmd) {
            $matchesCommand = str_contains(strtolower($cmd['command']), 'migrate');
            $matchesDescription = str_contains(strtolower($cmd['description']), 'migrate');
            expect($matchesCommand || $matchesDescription)->toBeTrue();
        }
    }
});

it('returns all commands when search is empty', function () {
    $component = new BrowserConsole();
    $component->mount();
    $component->commandSearch = '';

    $all = $component->getFilteredCommandReference();

    expect($all)->toBe($component->commandGroups);
});

it('loads logs when switching to logs mode', function () {
    $logPath = storage_path('logs/laravel.log');
    $logDir = dirname($logPath);

    if (! is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    File::put($logPath, "[2024-01-15 10:30:00] local.ERROR: Test error message\n");

    session(['console_authenticated' => true, 'console_last_activity' => time()]);

    Livewire::test(BrowserConsole::class)
        ->call('switchMode', 'logs')
        ->assertSet('mode', 'logs');

    // Clean up
    File::delete($logPath);
});

it('clears log file', function () {
    $logPath = storage_path('logs/laravel.log');
    $logDir = dirname($logPath);

    if (! is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    File::put($logPath, "Some log content\n");

    $component = new BrowserConsole();
    $component->clearLog();

    expect(File::get($logPath))->toBe('')
        ->and($component->logEntries)->toBe([]);

    // Clean up
    File::delete($logPath);
});

it('handles missing log file gracefully', function () {
    $logPath = storage_path('logs/laravel.log');

    if (File::exists($logPath)) {
        File::delete($logPath);
    }

    $component = new BrowserConsole();
    $component->loadLogs();

    expect($component->logEntries)->toBe([]);
});

it('renders the browser console view', function () {
    Livewire::test(BrowserConsole::class)
        ->assertStatus(200);
});
