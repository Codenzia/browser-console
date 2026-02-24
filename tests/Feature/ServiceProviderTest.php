<?php

use Codenzia\BrowserConsole\BrowserConsoleServiceProvider;
use Codenzia\BrowserConsole\Http\Middleware\ForceFileSession;
use Illuminate\Support\Facades\Route;

it('registers the service provider', function () {
    expect(app()->getProviders(BrowserConsoleServiceProvider::class))
        ->toHaveCount(1);
});

it('registers the console route', function () {
    $route = Route::getRoutes()->getByName('browser-console');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('console');
});

it('uses configurable path for the route', function () {
    config()->set('browser-console.path', 'admin-terminal');

    // Re-register routes by refreshing the provider
    $provider = new BrowserConsoleServiceProvider(app());
    $provider->packageBooted();

    $routes = collect(Route::getRoutes()->getRoutes())
        ->pluck('uri')
        ->toArray();

    expect($routes)->toContain('admin-terminal');
});

it('publishes the config file', function () {
    $configPath = config_path('browser-console.php');

    expect(file_exists(__DIR__ . '/../../config/browser-console.php'))->toBeTrue();
});

it('loads package config with defaults', function () {
    expect(config('browser-console.path'))->toBe('console')
        ->and(config('browser-console.session_timeout'))->toBe(1800)
        ->and(config('browser-console.gate'))->toBeNull()
        ->and(config('browser-console.exclude_middleware'))->toBe([]);
});

it('registers artisan commands', function () {
    expect(array_keys(\Artisan::all()))
        ->toContain('browser-console:create')
        ->toContain('browser-console:show');
});

it('prepends ForceFileSession middleware to web group', function () {
    $router = app('router');
    $middlewareGroups = $router->getMiddlewareGroups();

    expect($middlewareGroups['web'])->toContain(ForceFileSession::class);
});
