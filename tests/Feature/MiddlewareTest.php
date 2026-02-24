<?php

use Codenzia\BrowserConsole\Http\Middleware\ForceFileSession;
use Illuminate\Http\Request;

it('sets file session driver for console path', function () {
    config()->set('browser-console.path', 'console');

    $request = Request::create('/console');
    $middleware = new ForceFileSession();

    $middleware->handle($request, function () {
        expect(config('session.driver'))->toBe('file')
            ->and(config('session.cookie'))->toBe('browser-console-session')
            ->and(config('cache.default'))->toBe('file')
            ->and(config('browser-console.active'))->toBeTrue();

        return response('ok');
    });
});

it('sets file session driver for livewire requests from console', function () {
    config()->set('browser-console.path', 'console');

    $request = Request::create('/livewire/update');
    $request->headers->set('Referer', 'https://example.com/console');

    $middleware = new ForceFileSession();

    $middleware->handle($request, function () {
        expect(config('session.driver'))->toBe('file')
            ->and(config('browser-console.active'))->toBeTrue();

        return response('ok');
    });
});

it('does not modify session config for non-console paths', function () {
    config()->set('browser-console.path', 'console');
    config()->set('session.driver', 'database');

    $request = Request::create('/dashboard');
    $middleware = new ForceFileSession();

    $middleware->handle($request, function () {
        expect(config('session.driver'))->toBe('database')
            ->and(config('browser-console.active'))->toBeNull();

        return response('ok');
    });
});

it('uses configurable path for detection', function () {
    config()->set('browser-console.path', 'admin-terminal');

    $request = Request::create('/admin-terminal');
    $middleware = new ForceFileSession();

    $middleware->handle($request, function () {
        expect(config('session.driver'))->toBe('file');

        return response('ok');
    });
});

it('allows all IPs when allowed_ips is not configured', function () {
    config()->set('browser-console.path', 'console');
    config()->set('browser-console.allowed_ips', null);

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '192.168.1.50');

    $middleware = new ForceFileSession();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});

it('allows requests from whitelisted IPs', function () {
    config()->set('browser-console.path', 'console');
    config()->set('browser-console.allowed_ips', '127.0.0.1, 10.0.0.5');

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $middleware = new ForceFileSession();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});

it('blocks requests from non-whitelisted IPs', function () {
    config()->set('browser-console.path', 'console');
    config()->set('browser-console.allowed_ips', '127.0.0.1, 10.0.0.5');

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '192.168.1.50');

    $middleware = new ForceFileSession();

    $middleware->handle($request, function () {
        return response('ok');
    });
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('does not apply IP whitelist for non-console paths', function () {
    config()->set('browser-console.path', 'console');
    config()->set('browser-console.allowed_ips', '127.0.0.1');

    $request = Request::create('/dashboard');
    $request->server->set('REMOTE_ADDR', '192.168.1.50');

    $middleware = new ForceFileSession();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});

it('trims whitespace from allowed IPs', function () {
    config()->set('browser-console.path', 'console');
    config()->set('browser-console.allowed_ips', '  127.0.0.1  ,  10.0.0.5  ');

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '10.0.0.5');

    $middleware = new ForceFileSession();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});
