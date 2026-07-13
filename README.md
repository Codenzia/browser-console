# Browser Console — Web-based Artisan, shell, logs & debug for Laravel

[![Latest Version](https://img.shields.io/packagist/v/codenzia/browser-console.svg?style=flat-square)](https://packagist.org/packages/codenzia/browser-console)
[![PHP Version](https://img.shields.io/packagist/php-v/codenzia/browser-console.svg?style=flat-square)](https://packagist.org/packages/codenzia/browser-console)
[![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-ef4444?style=flat-square)](https://laravel.com)
[![Tests](https://img.shields.io/badge/tests-Pest%20v4-8b5cf6?style=flat-square)](https://pestphp.com)
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
| Laravel | `^12.0 \|\| ^13.0` |
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

    // Artisan mode policy (see "Artisan Mode Policy" below)
    'artisan' => [
        'read_only' => (bool) env('BROWSER_CONSOLE_READ_ONLY', false),
        'denylist' => ['db:wipe', 'migrate:fresh', 'migrate:reset', 'migrate:rollback', 'key:generate', 'down'],
        'allowlist' => ['*:status', 'route:list', 'about', '*:clear', 'optimize', 'optimize:clear'],
    ],

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
        'default_state' => env('BROWSER_CONSOLE_DEFAULT_STATE', 'local'), // 'on' | 'off' | 'local'
        'default_ttl' => (int) env('BROWSER_CONSOLE_DEFAULT_TTL', 10), // minutes used when --ttl is omitted
        'max_ttl' => (int) env('BROWSER_CONSOLE_MAX_TTL', 60),         // hard cap on --ttl in off/local mode
        'cache_key' => 'browser-console:state',
    ],

    // Structured audit log for login attempts, command runs, and switch ops
    'audit' => [
        'enabled' => (bool) env('BROWSER_CONSOLE_AUDIT', true),
        'channel' => env('BROWSER_CONSOLE_AUDIT_CHANNEL', 'stack'),
    ],

    // console() debug helper — enabled everywhere by default; opt out per env
    'debug' => [
        'enabled' => (bool) env('BROWSER_CONSOLE_DEBUG', true),
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

# Artisan read-only inspection mode (opt-in; full mode stays fully capable)
BROWSER_CONSOLE_READ_ONLY=false

# console() debug helper — enabled everywhere by default; set false to no-op it
BROWSER_CONSOLE_DEBUG=true

# public/bcd.php privileged fix actions — off unless explicitly opted in
BCD_FIX_ENABLED=false

# Kill switch — ships secure-by-default as 'local' (sealed outside local env,
# and always inert in production unless explicitly unlocked via :enable).
BROWSER_CONSOLE_DEFAULT_STATE=local    # on | off | local
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

#### Artisan Mode Policy

Full mode (the default) stays fully capable — every deployment command
(`migrate --force`, `db:seed --force`, `optimize`, `*:clear`, `storage:link`,
`shield:*`, …) runs unchanged. Two safety nets sit on top:

- **Denylist** — a small set of irreversible commands (`db:wipe`,
  `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `key:generate`, `down`)
  is blocked by default so a single leaked password cannot wipe the app. This is
  a configurable array, **not** a hard wall: edit or empty
  `browser-console.artisan.denylist` in `config/browser-console.php` to allow any
  of them. When a command is blocked the error message names the exact config to
  change.
- **Read-only mode** — set `BROWSER_CONSOLE_READ_ONLY=true` to restrict the
  Artisan tab to the `allowlist` (status/list/clear/optimize/about) and disable
  shell mode and all clear/write actions. Off by default; a pure inspection mode
  for touch-nothing debugging of a live deployment.

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

> **Platform note (Linux-only):** Shell mode targets POSIX servers. The command
> allowlist and reference (`ls`, `pwd`, `df`, `readlink`, `ln`, `which`, …) and
> the extended `PATH` are Unix-specific, so shell mode does not work on native
> Windows dev boxes (e.g. Herd). This is fine for Linux production/staging — the
> intended deployment target — but use the Artisan tab for local Windows work.

> **Note:** Long-running shell commands (e.g. `composer install`, `git pull`)
> hold a PHP-FPM worker for the duration of the command — up to 5 minutes for
> composer. Running several long commands concurrently can exhaust the worker
> pool on shared hosting. Each command is hard-capped by a per-process timeout,
> but operators should avoid issuing many simultaneous long-running commands.

### Logs Tab

- Browse Laravel log entries with level filtering (debug, info, warning, error, critical)
- Configurable line count (50, 100, 200, 500)
- Download or clear log files

> **Secret exposure:** `laravel.log` routinely contains secrets in stack traces
> and request payloads (DSNs, tokens, bearer headers, credentials). The Logs tab
> shows and downloads the file **verbatim, with no redaction** — under the
> single-password model, one credential exposes every secret ever logged. Treat
> console access accordingly, and prefer `off`/`local` kill-switch modes plus IP
> allowlisting on any environment whose logs may contain live secrets.

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

> **Production note:** Because debugging live deployments is a core purpose of
> this tool, `console()` is **enabled everywhere by default** — including
> production. That means any `console($request->all())` left in your code writes
> its payload (which may contain PII/secrets) to disk on every request. If you
> would rather it not, set `BROWSER_CONSOLE_DEBUG=false` in that environment and
> the helper becomes a safe no-op (the fluent chain still works, it just writes
> nothing).

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

The console ships secure-by-default with `BROWSER_CONSOLE_DEFAULT_STATE=local`:
the `/console` route is reachable only in your `local` environment and is
**always inert in `APP_ENV=production`** unless you explicitly unlock it with
`php artisan browser-console:enable --ttl=N` (a time-boxed window). For
production deployments you should also:

- Keep `BROWSER_CONSOLE_DEFAULT_STATE=local` (or `off`) and unlock on demand via
  `php artisan browser-console:enable --ttl=N`.
- Populate `BROWSER_CONSOLE_ALLOWED_IPS` with your operator IP(s).
- Remove `public/bcd.php` before going live — it is a standalone privileged
  diagnostics page. Remove it with `php artisan browser-console:diagnose --remove`.
  While present, its **read-only** diagnostics always work, but its privileged
  **write/fix actions** (create `.env`, `migrate --force`, chmod/symlink,
  `.htaccess` overwrite) stay off unless you explicitly set `BCD_FIX_ENABLED=true`
  in `.env` (and `APP_ENV` is not `production`). They are additionally force-off
  while `browser-console:disable` is in effect — that command now writes a
  `storage/logs/.bcd-locked` marker that bcd.php honors, so the kill switch also
  neuters this file; `browser-console:enable` (or `--remove`) clears it. The
  network-reachable read-only DB output redacts the DB host/name.

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
| `on` _(legacy — reachable whenever a password is set)_ | Reachable as long as the password is configured. `:disable` writes a sticky lockout that persists until `:enable`. Still inert in production unless explicitly unlocked. |
| `off` _(strict / recommended for hardened deployments)_ | 404 by default. Operator runs `php artisan browser-console:enable --ttl=10` to unlock for N minutes. **Auto-locks** when the timer elapses. |
| `local` _(shipped default — secure)_ | Behaves like `on` when `app()->environment('local')`, else `off`. |

Regardless of mode, the route is **always sealed in `APP_ENV=production`** unless a live `:enable` unlock is in effect — a hard, config-independent production guard.

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

This release makes the console **secure-by-default**. `BROWSER_CONSOLE_DEFAULT_STATE` now ships as `local` (was `on`), so `/console` is sealed outside your local environment and is **always inert in production** unless explicitly unlocked with `php artisan browser-console:enable --ttl=N`. **Breaking change:** installs that relied on the old always-on production behavior must either set `BROWSER_CONSOLE_DEFAULT_STATE=on` **and** run `:enable` in production, or (recommended) unlock on demand.

To harden a deployment:

1. Keep `BROWSER_CONSOLE_DEFAULT_STATE=local` (or set `off`) in `.env`.
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
