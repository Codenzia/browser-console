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

    'throttle' => (int) env('BROWSER_CONSOLE_THROTTLE', 60),

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

    /*
    |--------------------------------------------------------------------------
    | Kill Switch
    |--------------------------------------------------------------------------
    |
    | Cache-backed runtime switch controlling whether the /console route is
    | reachable. Three modes:
    |
    |   'on'    — Backwards-compatible default. Route reachable as long as
    |             the password is configured. `php artisan browser-console:disable`
    |             writes a force-disabled marker that persists until :enable
    |             is run.
    |
    |   'off'   — Strict. Route returns 404 by default. Operator must run
    |             `php artisan browser-console:enable --ttl=10` to unlock for
    |             N minutes (auto-locks when the TTL elapses).
    |
    |   'local' — Same as 'on' when app()->environment('local'), else behaves
    |             like 'off'.
    |
    | Invalid values fall back to 'on' with a one-shot warning logged to the
    | configured audit channel.
    |
    */

    'killswitch' => [
        'default_state' => env('BROWSER_CONSOLE_DEFAULT_STATE', 'on'),
        'default_ttl' => (int) env('BROWSER_CONSOLE_DEFAULT_TTL', 10),
        'max_ttl' => (int) env('BROWSER_CONSOLE_MAX_TTL', 60),
        'cache_key' => 'browser-console:state',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | When enabled, structured events are written to the configured log channel
    | and dispatched as Codenzia\BrowserConsole\Events\ConsoleAudit events for
    | hosts that want to forward them to SIEM/Slack/etc.
    |
    | Event names: console.login.success, console.login.failed,
    | console.command.executed, console.switch.enabled, console.switch.disabled,
    | console.switch.expired.
    |
    | Sensitive bits (password attempts, command output bodies) are never logged.
    |
    */

    'audit' => [
        'enabled' => (bool) env('BROWSER_CONSOLE_AUDIT', true),
        'channel' => env('BROWSER_CONSOLE_AUDIT_CHANNEL', 'stack'),
    ],

];
