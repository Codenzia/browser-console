<?php

declare(strict_types=1);

use Codenzia\BrowserConsole\Events\ConsoleAudit;
use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

describe('login audit hooks', function () {
    it('fires console.login.success on a successful login', function () {
        Event::fake();
        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'testpass123')
            ->call('authenticate');

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.login.success'
                && array_key_exists('ip', $e->context)
                && array_key_exists('user_agent', $e->context)
                && array_key_exists('route', $e->context);
        });
    });

    it('fires console.login.failed with reason=bad_password on wrong password', function () {
        Event::fake();
        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'wrong')
            ->call('authenticate');

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.login.failed'
                && ($e->context['reason'] ?? null) === 'bad_password';
        });
    });

    it('fires console.login.failed with reason=not_configured when credentials missing', function () {
        Event::fake();
        config()->set('browser-console.user', null);
        config()->set('browser-console.password', null);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'whatever')
            ->call('authenticate');

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.login.failed'
                && ($e->context['reason'] ?? null) === 'not_configured';
        });
    });

    it('never includes the password attempt in the audit context', function () {
        Event::fake();
        $hash = Hash::make('testpass123');
        config()->set('browser-console.user', 'admin');
        config()->set('browser-console.password', $hash);

        Livewire::test(BrowserConsole::class)
            ->set('username', 'admin')
            ->set('password', 'supersecret-attempt')
            ->call('authenticate');

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            $serialized = json_encode($e->context, JSON_THROW_ON_ERROR);

            return ! str_contains($serialized, 'supersecret-attempt');
        });
    });
});
