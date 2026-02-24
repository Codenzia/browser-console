<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Console Credentials
    |--------------------------------------------------------------------------
    |
    | Username and bcrypt-hashed password for the web-based console.
    | Created via: php artisan browser-console:create
    |
    */

    'user' => env('BROWSER_CONSOLE_USER'),
    'password' => env('BROWSER_CONSOLE_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Console URL Path
    |--------------------------------------------------------------------------
    |
    | The URL path where the console is accessible. Default: /console
    | Change this to a custom path for obscurity.
    |
    */

    'path' => env('BROWSER_CONSOLE_PATH', 'console'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum requests per minute. Set to 0 to disable rate limiting.
    |
    */

    'throttle' => (int) env('BROWSER_CONSOLE_THROTTLE', 600),

    /*
    |--------------------------------------------------------------------------
    | IP Whitelisting
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of allowed IPs. Leave empty to allow all IPs.
    | Example: '127.0.0.1,10.0.0.5,192.168.1.100'
    |
    */

    'allowed_ips' => env('BROWSER_CONSOLE_ALLOWED_IPS'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Gate
    |--------------------------------------------------------------------------
    |
    | Optional custom authentication callback. When set, this bypasses the
    | built-in username/password authentication entirely.
    |
    | Set in a ServiceProvider:
    |   config(['browser-console.gate' => fn($request) => $request->user()?->isAdmin()]);
    |
    */

    'gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Inactivity timeout in seconds. Default: 1800 (30 minutes).
    |
    */

    'session_timeout' => 1800,

    /*
    |--------------------------------------------------------------------------
    | Middleware to Exclude
    |--------------------------------------------------------------------------
    |
    | Array of middleware class names to exclude from console routes.
    | Useful for app-specific middleware (e.g. SetLocale, SetCurrency)
    | that shouldn't run on the console endpoint.
    |
    */

    'exclude_middleware' => [],

];
