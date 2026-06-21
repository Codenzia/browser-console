# Browser Console — Web-based Artisan, shell, logs & debug for Laravel

[![Latest Version](https://img.shields.io/packagist/v/codenzia/browser-console.svg?style=flat-square)](https://packagist.org/packages/codenzia/browser-console)
[![PHP Version](https://img.shields.io/packagist/php-v/codenzia/browser-console.svg?style=flat-square)](https://packagist.org/packages/codenzia/browser-console)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-ef4444?style=flat-square)](https://laravel.com)
[![Tests](https://img.shields.io/badge/tests-Pest%20v3-8b5cf6?style=flat-square)](https://pestphp.com)
[![License](https://img.shields.io/packagist/l/codenzia/browser-console.svg?style=flat-square)](LICENSE.md)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink?style=flat-square)](https://github.com/sponsors/codenzia)

A **web-based Artisan console, shell terminal, log viewer, and debug tool for Laravel** — accessible from your browser, no database required. Run artisan commands, tail logs, execute whitelisted shell commands (`git pull`, `composer install`, ...), and dump variables Ray-style via a `console()` helper — all from a single URL behind a password.

> **Why this exists.** Shared hosting and locked-down VPS deployments often have no SSH, broken Telescope, no Ray license, and no way to clear a stuck cache without a deploy. Browser Console gives you that escape hatch with secure cookie-based auth, no DB dependency (works in maintenance mode), and a standalone diagnostics page (`bcd.php`) that runs even when Laravel itself fails to boot.

> **Try it live:** A working integration is included in the [Codenzia plugins demo](https://github.com/Codenzia/plugins-demo) at `/admin/demo/browser-console`.

> **❤️ Sponsor this project** — If Browser Console saves you time, consider [sponsoring on GitHub](https://github.com/sponsors/codenzia) to support ongoing development.

## Features

- **Artisan Commands** — Run any artisan command with auto-complete reference panel
- **Shell Terminal** — Execute shell commands (git, composer, php, system tools) with real-time streaming output
- **Log Viewer** — Browse, filter, and download Laravel logs by level
- **Debug Tool** — Ray-like `console()` helper for inspecting variables with color coding, labels, and table views
- **Deployment Guide** — Step-by-step deployment reference panel with one-click command execution
- **No Database Required** — Cookie-based auth, works in maintenance mode
- **Secure** — Bcrypt password hashing, session timeout, rate limiting, IP whitelisting, custom auth gate
- **Runtime Kill Switch** — `/console` can be locked/unlocked via `php artisan browser-console:{enable,disable,status}`; strict mode returns an opaque 404 and auto-relocks after a configurable TTL (1–60 min)
- **Structured Audit Log** — Login attempts, command runs, and switch ops emit `ConsoleAudit` events to a configurable channel; password attempts and command output bodies are never logged
- **Zero Build Step** — Tailwind CSS via CDN, no npm/vite required

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.3` |
| Laravel | `^11.0 \|\| ^12.0 \|\| ^13.0` |
| Livewire | `^3.0 \|\| ^4.0` |

## Installation

```bash
composer require codenzia/browser-console
```

Publish the config file:

```bash
php artisan vendor:publish --tag=browser-console-config
```

Create your console credentials:

```bash
php artisan browser-console:create
```

Visit `/console` in your browser.

## Configuration

After publishing, the config file is at `config/browser-console.php`:

```php
return [
    // Credentials (set via browser-console:create command)
    'user' => env('BROWSER_CONSOLE_USER'),
    'password' => env('BROWSER_CONSOLE_PASSWORD'),

    // URL path (default: /console)
    'path' => env('BROWSER_CONSOLE_PATH', 'console'),

    // Rate limiting (requests per minute, 0 to disable)
    'throttle' => (int) env('BROWSER_CONSOLE_THROTTLE', 60),

    // IP whitelisting (comma-separated, empty = allow all)
    'allowed_ips' => env('BROWSER_CONSOLE_ALLOWED_IPS'),

    // Custom auth gate (bypasses password auth when set)
    'gate' => null,

    // Session inactivity timeout in seconds
    'session_timeout' => 1800,

    // Middleware to exclude from console routes
    'exclude_middleware' => [],

    // Kill switch — runtime on/off control for the /console route
    'killswitch' => [
        'default_state' => env('BROWSER_CONSOLE_DEFAULT_STATE', 'on'), // 'on' | 'off' | 'local'
        'default_ttl' => (int) env('BROWSER_CONSOLE_DEFAULT_TTL', 10), // minutes used when --ttl is omitted
        'max_ttl' => (int) env('BROWSER_CONSOLE_MAX_TTL', 60),         // hard cap on --ttl in off/local mode
        'cache_key' => 'browser-console:state',
    ],

    // Structured audit log for login attempts, command runs, and switch ops
    'audit' => [
        'enabled' => (bool) env('BROWSER_CONSOLE_AUDIT', true),
        'channel' => env('BROWSER_CONSOLE_AUDIT_CHANNEL', 'stack'),
    ],
];
```

### Environment Variables

Add these to your `.env` file (or use `browser-console:create`):

```env
BROWSER_CONSOLE_USER=admin
BROWSER_CONSOLE_PASSWORD='$2y$12$...'

# Optional
BROWSER_CONSOLE_PATH=console
BROWSER_CONSOLE_THROTTLE=60
BROWSER_CONSOLE_ALLOWED_IPS=127.0.0.1,10.0.0.5

# Kill switch — defaults preserve existing behavior; flip to 'off' to harden.
BROWSER_CONSOLE_DEFAULT_STATE=on    # on | off | local
BROWSER_CONSOLE_DEFAULT_TTL=10
BROWSER_CONSOLE_MAX_TTL=60

# Audit log
BROWSER_CONSOLE_AUDIT=true
BROWSER_CONSOLE_AUDIT_CHANNEL=stack
```

## Usage

### Artisan Tab

Type any artisan command or click one from the reference panel:

```
migrate:status
route:list
optimize:clear
cache:clear
```

Commands run as isolated subprocesses — no risk of corrupting the web response.

`tinker` is disabled in Artisan mode (it would allow arbitrary PHP/OS execution,
bypassing the shell allowlist), and shell operators and quotes are rejected.

### Shell Tab

Execute whitelisted shell commands:

```
git status
git pull
composer install --no-dev
php -v
ls -la
```

**Allowed commands:** composer, git, php, ls, pwd, whoami, readlink, cat, mkdir, chmod, ln, df, du, head, tail, wc, find, which

Shell operators (`;`, `&&`, `|`, `>`, etc.) are blocked for security.

> **Note:** Long-running shell commands (e.g. `composer install`, `git pull`)
> hold a PHP-FPM worker for the duration of the command — up to 5 minutes for
> composer. Running several long commands concurrently can exhaust the worker
> pool on shared hosting. Each command is hard-capped by a per-process timeout,
> but operators should avoid issuing many simultaneous long-running commands.

### Logs Tab

- Browse Laravel log entries with level filtering (debug, info, warning, error, critical)
- Configurable line count (50, 100, 200, 500)
- Download or clear log files

### Debug Tab

Add `console()` calls anywhere in your Laravel code:

```php
// Simple dump
console('Hello World');

// With label and color
console($user)->label('Current User')->green();

// Table view for arrays/objects
console($settings)->table();

// Multiple values
console($request->all(), $response)->label('API Call')->blue();
```

**Available colors:** `->green()`, `->blue()`, `->orange()`, `->red()`, `->purple()`

**Available methods:** `->label(string)`, `->table()`, `->color(string)`

Debug entries are written to `storage/logs/console-debug.log` as NDJSON and auto-pruned at 500KB.

## Security

### Authentication

- **Bcrypt hashing** — Passwords are stored as bcrypt hashes (never plaintext)
- **Constant-time credential check** — Username and password are compared in
  constant time to avoid user-enumeration timing leaks
- **Login brute-force limiter** — A dedicated per-IP limiter caps login attempts
  at 5 per minute, independent of the route throttle
- **Session timeout** — Auto-logout after 30 minutes of inactivity (configurable)
- **Rate limiting** — 60 requests/minute by default (configurable)
- **CSRF protection** — Standard Laravel web middleware

### Production hardening (strongly recommended)

The console ships with `BROWSER_CONSOLE_DEFAULT_STATE=on`, which makes the
`/console` route reachable as soon as a password is configured — including in
production. For production deployments you should:

- Set `BROWSER_CONSOLE_DEFAULT_STATE=local` (or `off`) so the route is sealed by
  default and only unlocked on demand via `php artisan browser-console:enable --ttl=N`.
- Populate `BROWSER_CONSOLE_ALLOWED_IPS` with your operator IP(s).
- Remove `public/bcd.php` before going live — it is a standalone privileged
  diagnostics page that is **NOT** governed by the kill switch and survives
  `browser-console:disable`. Remove it with `php artisan browser-console:diagnose --remove`.

### IP Whitelisting

Restrict console access to specific IPs:

```env
BROWSER_CONSOLE_ALLOWED_IPS=127.0.0.1,10.0.0.5,192.168.1.100
```

Leave empty to allow all IPs.

### Custom Auth Gate

Bypass the built-in password auth with your own logic:

```php
// In AppServiceProvider::boot()
config(['browser-console.gate' => function ($request) {
    return $request->user()?->hasRole('super_admin');
}]);
```

When the gate returns `true`, the login form is skipped entirely.

### Middleware Exclusion

Exclude app-specific middleware from console routes:

```php
// config/browser-console.php
'exclude_middleware' => [
    \App\Http\Middleware\SetLocale::class,
    \App\Http\Middleware\TrackVisitors::class,
],
```

### Kill Switch

A cache-backed runtime switch controls whether `/console` is reachable. When the switch is off the route returns an opaque **404** (not 403 — scanners cannot infer the route exists) before any auth code or `web` middleware runs.

Three modes via `BROWSER_CONSOLE_DEFAULT_STATE`:

| Mode | Route behavior |
|---|---|
| `on` _(shipped default — backwards-compatible)_ | Reachable as long as the password is configured. `:disable` writes a sticky lockout that persists until `:enable`. |
| `off` _(strict / recommended for hardened deployments)_ | 404 by default. Operator runs `php artisan browser-console:enable --ttl=10` to unlock for N minutes. **Auto-locks** when the timer elapses. |
| `local` | Behaves like `on` when `app()->environment('local')`, else `off`. |

Inspect and control the switch:

```bash
# Show the current state, expiry, and config health
php artisan browser-console:status

# Unlock for 10 minutes (TTL required in off/local mode; range 1..max_ttl)
php artisan browser-console:enable --ttl=10 --actor=ops@example.com

# Lock immediately (in on mode this is a sticky lockout)
php artisan browser-console:disable --actor=ops@example.com
```

Defense in depth: even when the switch resolves to "enabled", `/console` still returns 404 if the password is unset or empty. A misconfigured deployment never accidentally opens an unauthenticated shell.

Programmatic access is available via the `Console` facade:

```php
use Codenzia\BrowserConsole\Facades\Console;

Console::isEnabled();        // bool
Console::expiresAt();        // ?CarbonImmutable
Console::state();            // ['default_state' => ..., 'effective_state' => ..., ...]
Console::enable(10, 'ops@example.com');
Console::disable('ops@example.com');
```

### Audit Log

When `audit.enabled=true` (default), structured records are emitted to the configured log channel **and** dispatched as `Codenzia\BrowserConsole\Events\ConsoleAudit` events so hosts can forward them to SIEM / Slack / Sentry / etc.

| Event | Fired when | Key context |
|---|---|---|
| `console.login.success` | Password check passes | `ip`, `user_agent`, `route` |
| `console.login.failed` | Wrong password OR credentials not configured | `reason` (`bad_password` / `not_configured`), `ip`, `user_agent`, `route` |
| `console.command.executed` | After any artisan or shell command runs | `mode`, `command`, `exit_code`, `duration_ms`, `ip` |
| `console.switch.enabled` | `:enable` (CLI or facade) | `mode`, `ttl_minutes`, `expires_at`, `actor` |
| `console.switch.disabled` | `:disable` (CLI or facade) | `mode`, `actor` |
| `console.switch.expired` | A TTL elapses while reading state | `expired_at` |

Subscribe in an `EventServiceProvider`:

```php
use Codenzia\BrowserConsole\Events\ConsoleAudit;

protected $listen = [
    ConsoleAudit::class => [
        \App\Listeners\ForwardConsoleAuditsToSlack::class,
    ],
];
```

**Sensitive bits are never logged.** Password attempts, command output bodies, and any keys named `password`, `password_confirmation`, `output`, `output_body`, or `secret` are stripped before dispatch.

### Command Security

- **Input sanitization** — Both artisan and shell modes block shell operators (`;`, `&&`, `|`, `>`, backticks, `$()`, `${}`), control characters, and variable expansion (`$VAR`, `~`)
- **Allowlist-only (shell mode)** — Only whitelisted base commands can run
- **Dangerous pattern blocking** — `rm`, `git push`, `git reset --hard`, directory traversal, `/etc/`, etc.
- **Subprocess isolation** — Commands run as isolated subprocesses via `Symfony\Process`

## Artisan Commands

```bash
# Create or update console credentials
php artisan browser-console:create

# Show current username and verify password
php artisan browser-console:show
php artisan browser-console:show --verify

# Run deployment diagnostics from CLI
php artisan browser-console:diagnose

# Auto-fix detected issues (permissions, caches, missing files)
php artisan browser-console:diagnose --fix

# Force-refresh the web diagnostics page (public/bcd.php)
php artisan browser-console:diagnose --refresh

# Remove the diagnostics page from public/
php artisan browser-console:diagnose --remove

# Kill switch
php artisan browser-console:status                                 # show current state + config health
php artisan browser-console:enable --ttl=10 --actor=ops@example.com  # unlock for 10 minutes
php artisan browser-console:disable --actor=ops@example.com          # lock immediately
```

## Upgrade Notes

This release adds a runtime kill switch and a structured audit log. **No existing behavior changes by default:** `BROWSER_CONSOLE_DEFAULT_STATE` ships as `on`, identical to prior versions where the route was reachable whenever a password was configured.

To harden a deployment:

1. Set `BROWSER_CONSOLE_DEFAULT_STATE=off` in `.env`.
2. Re-publish the config or add the `killswitch` / `audit` blocks manually (`php artisan vendor:publish --tag=browser-console-config --force`).
3. Run `php artisan browser-console:enable --ttl=10` whenever you need access; the route auto-locks back to 404 after the TTL elapses.

No DB migrations are required. All state is cache-backed and self-expiring.

## Troubleshooting

If `/console` returns a **500 error** or doesn't load after deploying, use the built-in diagnostics page (`bcd.php`) to find the exact cause.

### Diagnostics Page (bcd.php)

During installation (`php artisan browser-console:install`), you'll be asked whether to publish the diagnostics page to `public/bcd.php`. It works without Laravel — even when the framework itself fails to start.

Visit `https://your-domain.com/bcd.php` and authenticate with your console password.

The page checks:
- **PHP Environment** — Version, required extensions, `proc_open()` availability
- **Laravel Structure** — `.env`, `APP_KEY`, vendor directory, config/route caches
- **File Permissions** — `storage/`, `bootstrap/cache/`, `public/`, and all subdirectories
- **Browser Console Package** — Installation, config, credentials, Livewire, `.htaccess`
- **Laravel Boot Test** — Attempts to bootstrap Laravel and shows the exact exception

> **Authentication:** The diagnostics page requires the `BROWSER_CONSOLE_PASSWORD` from your `.env` file. If no password is set, the page is locked entirely.

### CLI Diagnostics

If you have SSH access, run comprehensive diagnostics from the terminal:

```bash
php artisan browser-console:diagnose
```

This checks PHP environment, Laravel structure, file permissions, session & CSRF configuration, middleware stack (global + web group), session file read/write, CSRF token roundtrip, PHP settings (post_max_size, gc_maxlifetime, etc.), HTTPS & reverse proxy detection, cookie encryption order, and OPcache settings.

Use `--fix` to auto-repair common issues (directory permissions, cache clearing, missing files):

```bash
php artisan browser-console:diagnose --fix
```

### Managing bcd.php

```bash
# Force-refresh with the latest version from the package
php artisan browser-console:diagnose --refresh

# Remove from public/ when no longer needed
php artisan browser-console:diagnose --remove
```

To publish it later (or re-publish after removal):

```bash
php artisan browser-console:diagnose --refresh
```

## Uninstalling

Before removing the package, clean up published files:

```bash
php artisan browser-console:diagnose --remove
rm config/browser-console.php
composer remove codenzia/browser-console
```

You may also want to remove the `.env` variables (`BROWSER_CONSOLE_USER`, `BROWSER_CONSOLE_PASSWORD`, etc.).

## How It Works

The console uses **encrypted cookie-based auth** (`browser-console-auth`) instead of Laravel sessions. This means:

- Works without a database connection
- Works in maintenance mode
- Does not interfere with your main app's sessions, cookies, or cache
- Artisan commands run as isolated subprocesses via `Symfony\Process`
- The standalone diagnostics page (`bcd.php`) works even when Laravel fails to boot

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
