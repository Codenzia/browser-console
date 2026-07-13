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

/**
 * Mark the current request as authenticated for the browser console.
 * Mirrors ConsoleAuth::login()'s in-request state binding.
 */
function actingAsConsole(): void
{
    app()->instance('browser-console.auth.pending', true);
}

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

it('does not build the command reference for unauthenticated visitors (BROWSERCON-5)', function () {
    Livewire::test(BrowserConsole::class)
        ->assertSet('isAuthenticated', false)
        ->assertSet('commandGroups', []);
});

it('builds the command reference once authenticated (BROWSERCON-5)', function () {
    actingAsConsole();

    Livewire::test(BrowserConsole::class)
        ->assertSet('isAuthenticated', true)
        ->assertNotSet('commandGroups', []);
});

it('switches between modes', function () {
    actingAsConsole();

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
    actingAsConsole();

    Livewire::test(BrowserConsole::class)
        ->call('switchMode', 'invalid')
        ->assertSet('mode', 'artisan'); // Should remain default
});

it('fills command from reference panel', function () {
    actingAsConsole();

    Livewire::test(BrowserConsole::class)
        ->call('fillCommand', 'migrate:status')
        ->assertSet('command', 'migrate:status');
});

it('rejects fillCommand when not authenticated', function () {
    Livewire::test(BrowserConsole::class)
        ->call('fillCommand', 'migrate:status')
        ->assertForbidden();
});

it('clears history', function () {
    actingAsConsole();

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
    actingAsConsole();

    Livewire::test(BrowserConsole::class)
        ->set('command', '')
        ->call('runCommand')
        ->assertSet('history', []);
});

/**
 * Invoke a now-protected reference-builder method for assertions. The methods
 * are protected (CON-001) so unauthenticated clients cannot call them over the
 * wire; a bound closure reaches them for in-process testing.
 */
function callProtected(BrowserConsole $component, string $method): mixed
{
    return (function () use ($method) {
        return $this->{$method}();
    })->call($component);
}

it('does not expose reference-builder methods to unauthenticated clients (CON-001)', function () {
    foreach (['getCommandReference', 'getShellCommandReference', 'getDeploymentGuide', 'getFilteredCommandReference'] as $method) {
        // Now protected: Livewire refuses to invoke it as a client action, so an
        // unauthenticated caller can never reach the command/seeder inventory.
        expect(fn () => Livewire::test(BrowserConsole::class)->call($method))
            ->toThrow('not found on component');
    }
});

it('returns command reference groups', function () {
    $component = new BrowserConsole;
    $component->mount();
    $groups = callProtected($component, 'getCommandReference');

    expect($groups)->toBeArray()
        ->and(array_keys($groups))->each->toBeString();
});

it('returns shell command reference groups', function () {
    $component = new BrowserConsole;
    $groups = callProtected($component, 'getShellCommandReference');

    expect($groups)->toBeArray()
        ->and($groups)->toHaveKeys([__('Composer'), __('Git'), __('PHP Info'), __('System')]);
});

it('returns deployment guide', function () {
    $component = new BrowserConsole;
    $guide = callProtected($component, 'getDeploymentGuide');

    expect($guide)->toBeArray()
        ->and($guide)->toHaveKeys([__('Fresh Deployment'), __('Re-deployment / Update')]);
});

it('filters command reference by search term', function () {
    $component = new BrowserConsole;
    $component->mount();
    $component->commandSearch = 'migrate';
    $filtered = callProtected($component, 'getFilteredCommandReference');

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
    $component = new BrowserConsole;
    $component->mount();
    $component->commandSearch = '';

    $all = callProtected($component, 'getFilteredCommandReference');

    expect($all)->toBe($component->commandGroups);
});

it('loads logs when switching to logs mode', function () {
    $logPath = storage_path('logs/laravel.log');
    $logDir = dirname($logPath);

    if (! is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    File::put($logPath, "[2024-01-15 10:30:00] local.ERROR: Test error message\n");

    actingAsConsole();

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

    actingAsConsole();

    $component = new BrowserConsole;
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

    actingAsConsole();

    $component = new BrowserConsole;
    $component->loadLogs();

    expect($component->logEntries)->toBe([]);
});

it('reads tail of large log files without loading entire file', function () {
    $logPath = storage_path('logs/laravel.log');
    $logDir = dirname($logPath);

    if (! is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Write a log file larger than the 256KB small-file threshold
    $handle = fopen($logPath, 'w');
    for ($i = 0; $i < 2000; $i++) {
        $line = sprintf("[2024-01-15 10:%02d:%02d] local.ERROR: Test error message number %d\n", intdiv($i, 60) % 60, $i % 60, $i);
        // Pad each line to ~200 bytes to exceed 256KB
        fwrite($handle, $line.str_repeat(' ', 150)."\n");
    }
    fclose($handle);

    expect(filesize($logPath))->toBeGreaterThan(262144); // > 256KB

    actingAsConsole();

    $component = new BrowserConsole;
    $component->logLines = 50;
    $component->logLevel = 'all';
    $component->loadLogs();

    // Should have parsed entries from the tail, not OOM from loading the whole file
    expect($component->logEntries)->toBeArray()
        ->and(count($component->logEntries))->toBeLessThanOrEqual(50)
        ->and(count($component->logEntries))->toBeGreaterThan(0);

    // Clean up
    File::delete($logPath);
});

it('renders the browser console view', function () {
    Livewire::test(BrowserConsole::class)
        ->assertStatus(200);
});

it('rejects log/debug actions when not authenticated', function () {
    // No actingAsConsole() — these must be gated behind authentication.
    foreach (['loadLogs', 'clearLog', 'loadDebugEntries', 'clearDebugEntries', 'clearHistory'] as $method) {
        Livewire::test(BrowserConsole::class)
            ->call($method)
            ->assertForbidden();
    }
});

it('rejects switchMode when not authenticated', function () {
    Livewire::test(BrowserConsole::class)
        ->call('switchMode', 'logs')
        ->assertForbidden();
});

it('rejects downloadLog when not authenticated', function () {
    Livewire::test(BrowserConsole::class)
        ->call('downloadLog')
        ->assertForbidden();
});

it('notifies instead of a silent 204 when there is no log to download (CON-010)', function () {
    $logPath = storage_path('logs/laravel.log');
    if (File::exists($logPath)) {
        File::delete($logPath);
    }

    actingAsConsole();

    Livewire::test(BrowserConsole::class)
        ->call('downloadLog')
        ->assertDispatched('console-notice');
});

it('does not truncate an artisan command whose argument contains "artisan " (CON-007)', function () {
    actingAsConsole();

    // Old code used Str::after($input, 'artisan '), which would truncate this to
    // just "foo". The prefix is only stripped at position 0 now.
    $component = Livewire::test(BrowserConsole::class)
        ->set('command', 'route:list --path=artisan foo')
        ->call('runCommand');

    $history = $component->get('history');

    expect($history)->not->toBeEmpty()
        ->and($history[0]['command'])->toBe('route:list --path=artisan foo');
});
