# Browser Console

[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink)](https://github.com/sponsors/codenzia)

A web-based Artisan console, shell terminal, log viewer, and debug tool for Laravel — accessible from your browser, no database required.

> **❤️ Sponsor this project** — If Browser Console saves you time, consider [sponsoring on GitHub](https://github.com/sponsors/codenzia) to support ongoing development.

## Features

- **Artisan Commands** — Run any artisan command with auto-complete reference panel
- **Shell Terminal** — Execute shell commands (git, composer, php, system tools) with real-time streaming output
- **Log Viewer** — Browse, filter, and download Laravel logs by level
- **Debug Tool** — Ray-like `console()` helper for inspecting variables with color coding, labels, and table views
- **Deployment Guide** — Step-by-step deployment reference panel with one-click command execution
- **No Database Required** — File-based sessions, works in maintenance mode
- **Secure** — Bcrypt password hashing, session timeout, rate limiting, IP whitelisting, custom auth gate
- **Zero Build Step** — Tailwind CSS via CDN, no npm/vite required

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Livewire 3.x

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
    'throttle' => (int) env('BROWSER_CONSOLE_THROTTLE', 600),

    // IP whitelisting (comma-separated, empty = allow all)
    'allowed_ips' => env('BROWSER_CONSOLE_ALLOWED_IPS'),

    // Custom auth gate (bypasses password auth when set)
    'gate' => null,

    // Session inactivity timeout in seconds
    'session_timeout' => 1800,

    // Middleware to exclude from console routes
    'exclude_middleware' => [],
];
```

### Environment Variables

Add these to your `.env` file (or use `browser-console:create`):

```env
BROWSER_CONSOLE_USER=admin
BROWSER_CONSOLE_PASSWORD='$2y$12$...'

# Optional
BROWSER_CONSOLE_PATH=console
BROWSER_CONSOLE_THROTTLE=600
BROWSER_CONSOLE_ALLOWED_IPS=127.0.0.1,10.0.0.5
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
- **Session timeout** — Auto-logout after 30 minutes of inactivity (configurable)
- **Rate limiting** — 600 requests/minute by default (configurable)
- **CSRF protection** — Standard Laravel web middleware

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

### Shell Command Security

- **Allowlist-only** — Only whitelisted base commands can run
- **No shell operators** — `;`, `&&`, `|`, `>`, backticks, `$()` are all blocked
- **Dangerous pattern blocking** — `rm`, `git push`, `git reset --hard`, directory traversal, `/etc/`, etc.
- **No variable expansion** — `$VAR` and `~` are blocked

## Artisan Commands

```bash
# Create or update console credentials
php artisan browser-console:create

# Show current username and verify password
php artisan browser-console:show
php artisan browser-console:show --verify
```

## How It Works

The console uses **file-based sessions** (not your database session driver) with a separate cookie (`browser-console-session`). This means:

- Works without a database connection
- Works in maintenance mode
- Does not interfere with your main app's sessions
- Artisan commands run as isolated subprocesses via `Symfony\Process`

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
