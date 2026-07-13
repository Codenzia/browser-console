<?php

use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', Hash::make('testpass123'));

    // Authenticate the request (mirrors ConsoleAuth::login()).
    app()->instance('browser-console.auth.pending', true);
});

/**
 * Evaluate the artisan policy for a base command without executing a subprocess.
 */
function policyDecision(string $command): bool|string
{
    return (function () use ($command) {
        return $this->rejectDisallowedArtisanCommand($command);
    })->call(new BrowserConsole);
}

it('denylists destructive artisan commands by default (CON-003)', function () {
    foreach (['db:wipe', 'migrate:fresh', 'migrate:reset', 'migrate:rollback', 'key:generate', 'down'] as $command) {
        expect(policyDecision($command))->toBeString()
            ->toContain('browser-console.artisan.denylist');
    }
});

it('keeps normal deployment commands runnable in full mode (CON-003)', function () {
    foreach (['migrate', 'db:seed', 'optimize', 'config:clear', 'route:list', 'storage:link', 'shield:generate', 'about'] as $command) {
        expect(policyDecision($command))->toBeTrue();
    }
});

it('lets an operator re-enable a denylisted command via config (CON-003)', function () {
    config()->set('browser-console.artisan.denylist', []);

    expect(policyDecision('migrate:fresh'))->toBeTrue();
});

it('rejects a denylisted command through runCommand with an actionable message (CON-003)', function () {
    $history = Livewire::test(BrowserConsole::class)
        ->set('command', 'db:wipe --force')
        ->call('runCommand')
        ->get('history');

    expect($history)->not->toBeEmpty();

    $last = end($history);
    expect($last['status'])->toBe('error')
        ->and($last['output'])->toContain('browser-console.artisan.denylist');
});

describe('read-only mode (CON-003)', function () {
    beforeEach(function () {
        config()->set('browser-console.artisan.read_only', true);
    });

    it('allows only allowlisted commands', function () {
        expect(policyDecision('migrate:status'))->toBeTrue()
            ->and(policyDecision('route:list'))->toBeTrue()
            ->and(policyDecision('cache:clear'))->toBeTrue()
            ->and(policyDecision('optimize'))->toBeTrue();

        expect(policyDecision('migrate'))->toBeString()->toContain('allowlist');
        expect(policyDecision('db:seed'))->toBeString();
    });

    it('disables shell commands', function () {
        $history = Livewire::test(BrowserConsole::class)
            ->set('mode', 'shell')
            ->set('command', 'ls -la')
            ->call('runCommand')
            ->get('history');

        $last = end($history);
        expect($last['status'])->toBe('error')
            ->and($last['output'])->toContain('Read-only mode');
    });

    it('disables clearing the log file', function () {
        Livewire::test(BrowserConsole::class)
            ->call('clearLog')
            ->assertDispatched('console-notice');
    });
});
