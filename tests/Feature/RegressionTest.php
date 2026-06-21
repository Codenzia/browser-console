<?php

declare(strict_types=1);

/**
 * Regression gate — pre-existing behaviors under default_state=on.
 *
 * This file asserts the contract every existing install relies on. If any of
 * these fail, the kill switch / audit additions broke an existing path and
 * must be reworked, not weakened. The new code is dormant under the shipped
 * default (default_state=on) — the only observable change should be the
 * audit records appearing in the log channel.
 */

use Codenzia\BrowserConsole\Events\ConsoleAudit;
use Codenzia\BrowserConsole\Http\Middleware\ConsoleGate;
use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Cache::flush();
});

describe('default_state=on (shipped default) preserves the existing route contract', function () {
    it('serves the console login form when password is configured', function () {
        config()->set('browser-console.killswitch.default_state', 'on');

        $response = $this->get('/console');

        $response->assertOk();
    });

    it('blocks the route when password is not configured (defense in depth)', function () {
        config()->set('browser-console.user', null);
        config()->set('browser-console.password', null);
        config()->set('browser-console.killswitch.default_state', 'on');

        $response = $this->get('/console');

        $response->assertNotFound();
    });
});

describe('existing auth flow still works unchanged', function () {
    it('authenticates with valid credentials', function () {
        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'testpass123')
            ->call('authenticate')
            ->assertSet('isAuthenticated', true);
    });

    it('rejects invalid credentials and clears the password field', function () {
        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'wrong')
            ->call('authenticate')
            ->assertSet('isAuthenticated', false)
            ->assertSet('password', '')
            ->assertSee('Invalid credentials');
    });

    it('shows "Console access not configured" when credentials are missing', function () {
        config()->set('browser-console.user', null);
        config()->set('browser-console.password', null);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'whatever')
            ->call('authenticate')
            ->assertSet('isAuthenticated', false)
            ->assertSee('Console access not configured');
    });

    it('honours the gate closure short-circuit', function () {
        config()->set('browser-console.gate', fn ($request) => true);

        Livewire::test(BrowserConsole::class)
            ->assertSet('isAuthenticated', true);

        config()->set('browser-console.gate', fn ($request) => false);

        Livewire::test(BrowserConsole::class)
            ->assertSet('isAuthenticated', false);
    });
});

describe('ConsoleGate middleware behavior is unchanged', function () {
    it('still allows all IPs when allowed_ips is null', function () {
        config()->set('browser-console.allowed_ips', null);

        $request = Request::create('/console');
        $request->server->set('REMOTE_ADDR', '203.0.113.50');

        $middleware = new ConsoleGate;

        $response = $middleware->handle($request, fn () => response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('still blocks non-whitelisted IPs', function () {
        config()->set('browser-console.allowed_ips', '127.0.0.1');

        $request = Request::create('/console');
        $request->server->set('REMOTE_ADDR', '203.0.113.50');

        $middleware = new ConsoleGate;

        $middleware->handle($request, fn () => response('ok'));
    })->throws(HttpException::class);

    it('still sets the browser-console.active flag', function () {
        $request = Request::create('/console');
        $middleware = new ConsoleGate;

        $middleware->handle($request, function () {
            expect(config('browser-console.active'))->toBeTrue();

            return response('ok');
        });
    });
});

describe('existing commands continue to register and run', function () {
    it('keeps browser-console:create registered', function () {
        expect(array_keys(Artisan::all()))->toContain('browser-console:create');
    });

    it('keeps browser-console:show registered', function () {
        expect(array_keys(Artisan::all()))->toContain('browser-console:show');
    });

    it('keeps browser-console:diagnose registered', function () {
        expect(array_keys(Artisan::all()))->toContain('browser-console:diagnose');
    });
});

describe('audit hooks are additive, not load-bearing', function () {
    it('fires console.login.success without altering the success outcome', function () {
        Event::fake();

        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'testpass123')
            ->call('authenticate')
            ->assertSet('isAuthenticated', true);

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $event): bool {
            return $event->name === 'console.login.success';
        });
    });

    it('respects audit.enabled=false (no events dispatched)', function () {
        Event::fake();
        config()->set('browser-console.audit.enabled', false);

        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'testpass123')
            ->call('authenticate')
            ->assertSet('isAuthenticated', true);

        Event::assertNotDispatched(ConsoleAudit::class);
    });
});
