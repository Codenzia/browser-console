<?php

declare(strict_types=1);

use Codenzia\BrowserConsole\Facades\Console;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

describe('default_state=off', function () {
    beforeEach(function () {
        config()->set('browser-console.killswitch.default_state', 'off');
    });

    it('returns 404 on /console with no enable', function () {
        $response = $this->get('/console');

        $response->assertNotFound();
    });

    it('returns 200 after Console::enable(10)', function () {
        Console::enable(10);

        $response = $this->get('/console');

        $response->assertOk();
    });

    it('returns 404 after Console::disable()', function () {
        Console::enable(10);
        Console::disable();

        $response = $this->get('/console');

        $response->assertNotFound();
    });

    it('does not leak the route name or package token in the 404 body', function () {
        $response = $this->get('/console');

        $response->assertNotFound();
        $body = (string) $response->getContent();
        expect($body)
            ->not->toContain('browser-console')
            ->not->toContain('BrowserConsole');
    });
});

describe('default_state=on (backwards-compatible default)', function () {
    beforeEach(function () {
        config()->set('browser-console.killswitch.default_state', 'on');
    });

    it('returns 200 with no cache entry (preserves current behavior)', function () {
        $response = $this->get('/console');

        $response->assertOk();
    });

    it('returns 404 after Console::disable() writes a force_disabled lockout', function () {
        Console::disable();

        $response = $this->get('/console');

        $response->assertNotFound();
    });

    it('Console::enable() clears the lockout in on mode', function () {
        Console::disable();
        Console::enable();

        $response = $this->get('/console');

        $response->assertOk();
    });
});

describe('default_state=local', function () {
    it('in local env, route is reachable by default', function () {
        config()->set('browser-console.killswitch.default_state', 'local');
        app()['env'] = 'local';

        $response = $this->get('/console');

        $response->assertOk();
    });

    it('in non-local env, route returns 404 until :enable', function () {
        config()->set('browser-console.killswitch.default_state', 'local');
        app()['env'] = 'production';

        $this->get('/console')->assertNotFound();

        Console::enable(10);

        $this->get('/console')->assertOk();
    });
});

describe('secure-by-default production guard (BROWSERCON-2)', function () {
    it('returns 404 in production with the shipped default (unset default_state)', function () {
        // No default_state override — uses the package default, which now ships
        // as 'local' (secure). In production that seals the route.
        app()['env'] = 'production';

        $this->get('/console')->assertNotFound();
    });

    it('is inert in production even with default_state=on and no explicit enable', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        app()['env'] = 'production';

        $this->get('/console')->assertNotFound();
    });
});

describe('password fail-closed at the route layer', function () {
    it('returns 404 when password is empty even with default_state=on', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        config()->set('browser-console.password', '');

        $response = $this->get('/console');

        $response->assertNotFound();
    });

    it('returns 404 when password is null even with explicit enable', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        config()->set('browser-console.password', null);
        // Note: Console::enable() should still throw nothing — fail-closed lives in isEnabled().
        Console::enable(10);

        $response = $this->get('/console');

        $response->assertNotFound();
    });
});
