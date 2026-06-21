<?php

declare(strict_types=1);

use Codenzia\BrowserConsole\Events\ConsoleAudit;
use Codenzia\BrowserConsole\Facades\Console;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Cache::flush();
});

describe('browser-console:enable', function () {
    it('is registered with Artisan', function () {
        expect(array_keys(Artisan::all()))->toContain('browser-console:enable');
    });

    it('enables in off mode with default TTL and emits audit', function () {
        Event::fake();
        config()->set('browser-console.killswitch.default_state', 'off');
        config()->set('browser-console.killswitch.default_ttl', 10);

        $this->artisan('browser-console:enable')
            ->expectsOutputToContain('Browser Console enabled.')
            ->assertSuccessful();

        expect(Console::isEnabled())->toBeTrue();

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.enabled'
                && $e->context['ttl_minutes'] === 10;
        });
    });

    it('honours --ttl flag in off mode', function () {
        config()->set('browser-console.killswitch.default_state', 'off');

        $this->artisan('browser-console:enable --ttl=15')
            ->assertSuccessful();

        $expiresAt = Console::expiresAt();
        expect($expiresAt)->not->toBeNull();
        $remaining = $expiresAt->getTimestamp() - time();
        expect($remaining)->toBeGreaterThan(14 * 60)->toBeLessThanOrEqual(15 * 60 + 5);
    });

    it('rejects --ttl=0 in off mode', function () {
        config()->set('browser-console.killswitch.default_state', 'off');

        $this->artisan('browser-console:enable --ttl=0')
            ->expectsOutputToContain('TTL must be between')
            ->assertFailed();

        expect(Console::isEnabled())->toBeFalse();
    });

    it('rejects --ttl above max_ttl', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        config()->set('browser-console.killswitch.max_ttl', 60);

        $this->artisan('browser-console:enable --ttl=61')
            ->expectsOutputToContain('TTL must be between 1 and 60')
            ->assertFailed();
    });

    it('rejects non-numeric --ttl', function () {
        config()->set('browser-console.killswitch.default_state', 'off');

        $this->artisan('browser-console:enable --ttl=banana')
            ->assertFailed();
    });

    it('in on mode, ignores --ttl and clears any lockout', function () {
        Event::fake();
        config()->set('browser-console.killswitch.default_state', 'on');
        Console::disable();

        $this->artisan('browser-console:enable --ttl=999')
            ->expectsOutputToContain('kill switch: cleared')
            ->assertSuccessful();

        expect(Console::isEnabled())->toBeTrue();

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.enabled'
                && $e->context['mode'] === 'on'
                && $e->context['ttl_minutes'] === null;
        });
    });

    it('records --actor in the audit event when provided', function () {
        Event::fake();
        config()->set('browser-console.killswitch.default_state', 'off');

        $this->artisan('browser-console:enable --ttl=10 --actor=ops@example.com')
            ->assertSuccessful();

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.enabled'
                && $e->context['actor'] === 'ops@example.com';
        });
    });
});

describe('browser-console:disable', function () {
    it('is registered with Artisan', function () {
        expect(array_keys(Artisan::all()))->toContain('browser-console:disable');
    });

    it('in on mode writes a sticky lockout', function () {
        Event::fake();
        config()->set('browser-console.killswitch.default_state', 'on');

        $this->artisan('browser-console:disable')
            ->expectsOutputToContain('Browser Console locked.')
            ->assertSuccessful();

        expect(Console::isEnabled())->toBeFalse();

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.disabled'
                && $e->context['mode'] === 'on';
        });
    });

    it('in off mode clears any active enable (idempotent)', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        Console::enable(10);
        expect(Console::isEnabled())->toBeTrue();

        $this->artisan('browser-console:disable')
            ->expectsOutputToContain('Browser Console disabled.')
            ->assertSuccessful();

        expect(Console::isEnabled())->toBeFalse();
    });

    it('records --actor in the audit event when provided', function () {
        Event::fake();

        $this->artisan('browser-console:disable --actor=ops@example.com')
            ->assertSuccessful();

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.disabled'
                && $e->context['actor'] === 'ops@example.com';
        });
    });
});

describe('browser-console:status', function () {
    it('is registered with Artisan', function () {
        expect(array_keys(Artisan::all()))->toContain('browser-console:status');
    });

    it('reports disabled when default_state=off and no enable', function () {
        config()->set('browser-console.killswitch.default_state', 'off');

        $exitCode = \Illuminate\Support\Facades\Artisan::call('browser-console:status');
        $output = (string) \Illuminate\Support\Facades\Artisan::output();

        expect($exitCode)->toBe(0);
        expect($output)
            ->toContain('default_state:')
            ->toContain('off')
            ->toContain('effective_state:')
            ->toContain('disabled');
    });

    it('reports enabled with expiry after :enable in off mode', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        Console::enable(10);

        $this->artisan('browser-console:status')
            ->expectsOutputToContain('enabled')
            ->assertSuccessful();
    });

    it('warns when password is not configured', function () {
        config()->set('browser-console.password', null);

        $this->artisan('browser-console:status')
            ->expectsOutputToContain('Password is not configured')
            ->assertSuccessful();
    });

    it('never prints the password hash', function () {
        $hash = config('browser-console.password');
        expect($hash)->toBeString()->not->toBe('');

        $output = '';
        $exitCode = $this->artisan('browser-console:status')
            ->execute();

        // The full transcript is in the test harness's output buffer. Re-run
        // and capture by routing through the Console renderer.
        ob_start();
        \Illuminate\Support\Facades\Artisan::call('browser-console:status');
        $output = (string) \Illuminate\Support\Facades\Artisan::output();
        ob_end_clean();

        expect($output)->not->toContain($hash);
        expect($exitCode)->toBe(0);
    });
});
