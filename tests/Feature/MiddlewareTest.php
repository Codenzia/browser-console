<?php

use Codenzia\BrowserConsole\Http\Middleware\ConsoleGate;
use Illuminate\Http\Request;

it('sets browser-console.active config flag', function () {
    $request = Request::create('/console');
    $middleware = new ConsoleGate();

    $middleware->handle($request, function () {
        expect(config('browser-console.active'))->toBeTrue();

        return response('ok');
    });
});

it('suppresses display_errors', function () {
    $request = Request::create('/console');
    $middleware = new ConsoleGate();

    $middleware->handle($request, function () {
        expect(ini_get('display_errors'))->toBe('0');

        return response('ok');
    });
});

it('allows all IPs when allowed_ips is not configured', function () {
    config()->set('browser-console.allowed_ips', null);

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '192.168.1.50');

    $middleware = new ConsoleGate();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});

it('allows requests from whitelisted IPs', function () {
    config()->set('browser-console.allowed_ips', '127.0.0.1, 10.0.0.5');

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $middleware = new ConsoleGate();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});

it('blocks requests from non-whitelisted IPs', function () {
    config()->set('browser-console.allowed_ips', '127.0.0.1, 10.0.0.5');

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '192.168.1.50');

    $middleware = new ConsoleGate();

    $middleware->handle($request, function () {
        return response('ok');
    });
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('trims whitespace from allowed IPs', function () {
    config()->set('browser-console.allowed_ips', '  127.0.0.1  ,  10.0.0.5  ');

    $request = Request::create('/console');
    $request->server->set('REMOTE_ADDR', '10.0.0.5');

    $middleware = new ConsoleGate();

    $response = $middleware->handle($request, function () {
        return response('ok');
    });

    expect($response->getStatusCode())->toBe(200);
});
