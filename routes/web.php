<?php

use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Illuminate\Support\Facades\Route;

// Browser Console route — no database dependency.
// Uses its own middleware stack with file-based sessions.
// Throttle configurable via BROWSER_CONSOLE_THROTTLE in .env (default 600 req/min).
$path = config('browser-console.path', 'console');
$throttle = config('browser-console.throttle', 600);
$excludeMiddleware = config('browser-console.exclude_middleware', []);

$route = Route::get($path, BrowserConsole::class)
    ->name('browser-console');

if ($excludeMiddleware) {
    $route->withoutMiddleware($excludeMiddleware);
}

if ($throttle > 0) {
    $route->middleware("throttle:{$throttle},1");
}
