# Manual Smoke — kill switch + audit

These steps cannot be automated cleanly (real `.env`, real cache backend, host integration). Run them in a scratch Laravel project that has `codenzia/browser-console` installed before tagging a release.

## Setup

1. Fresh Laravel app with the package installed.
2. `php artisan vendor:publish --tag=browser-console-config --force`.
3. `php artisan browser-console:create` — pick a 12+ character password.
4. Confirm credentials are written to `.env`.

## 1 — Backwards-compatible default (`default_state=on`)

```
php artisan browser-console:status
```

Expect: `default_state: on`, `effective_state: enabled`, `password_configured: yes`.

Visit `/console` → login form renders. Enter password → console UI appears. Run `php artisan about` inside the console — see output. Confirms zero behavior change vs. prior versions.

Now lock it:

```
php artisan browser-console:disable
```

Refresh `/console` → **404** (opaque, no leakage of route name in body).

```
php artisan browser-console:enable
```

Refresh `/console` → 200.

## 2 — Strict mode (`default_state=off`)

In `.env`:

```
BROWSER_CONSOLE_DEFAULT_STATE=off
```

Clear config cache: `php artisan config:clear`.

```
php artisan browser-console:status
```

Expect: `default_state: off`, `effective_state: disabled`, `expires_at: —`.

Visit `/console` → **404**. The route does not exist as far as scanners are concerned.

```
php artisan browser-console:enable --ttl=10
```

Visit `/console` → 200. Status now shows `expires_at` in 10 minutes.

Wait 11 minutes (or set TTL=2 for a faster smoke). Visit `/console` → **404** again. The auto-lock fired without any human intervention. Cache entry should be evicted (`Cache::get('browser-console:state')` returns null).

## 3 — TTL validation

```
php artisan browser-console:enable --ttl=0    # → error, exit code 1
php artisan browser-console:enable --ttl=61   # → error, exit code 1 (with default max_ttl=60)
php artisan browser-console:enable --ttl=banana  # → error, exit code 1
```

None of the above should mutate the cache state. `:status` after each should show `effective_state: disabled` (assuming we just came out of an expired enable).

## 4 — Password fail-closed

Temporarily blank the password in `.env`:

```
BROWSER_CONSOLE_PASSWORD=
```

Clear config cache and try:

```
php artisan browser-console:enable --ttl=10
```

Visit `/console` → **404** (even though `:enable` "succeeded"). Confirms the `isEnabled()` defense-in-depth password check.

Restore the password before continuing.

## 5 — Audit log end-to-end

Confirm `BROWSER_CONSOLE_AUDIT=true` and the configured log channel writes somewhere you can tail (`storage/logs/laravel.log` by default).

Trigger the events:

1. Submit a wrong password at `/console`. → Expect `console.login.failed` with `reason=bad_password`, `ip`, `user_agent`, `route` in the log.
2. Submit the correct password. → Expect `console.login.success` with `ip`, `user_agent`, `route`.
3. Run `php artisan about` inside the console. → Expect `console.command.executed` with `mode=artisan`, `command=about`, `exit_code=0`, `duration_ms`, `ip`.
4. Switch to shell mode, run `git status`. → Expect `console.command.executed` with `mode=shell`, `command=git status`, `exit_code=0`.
5. From a terminal, run `php artisan browser-console:disable --actor=ops@example.com`. → Expect `console.switch.disabled` with `mode`, `actor=ops@example.com`.
6. `:enable --ttl=10 --actor=ops@example.com`. → Expect `console.switch.enabled` with `mode`, `ttl_minutes=10`, `expires_at`, `actor`.

**Verify nothing sensitive leaks:** grep the log channel for the password you typed in step 1. It must not appear. Same for command output bodies — only the command string and exit code should be present.

## 6 — Audit disable

Set `BROWSER_CONSOLE_AUDIT=false`, clear config cache, repeat steps 1-2 from §5. **No** new lines should appear in the log channel and no `ConsoleAudit` events should fire (verify by registering a listener in a one-off `AppServiceProvider::boot` that throws on receipt).

## 7 — Sealed route does not leak

With `default_state=off` and no enable in effect:

```
curl -sv https://host/console 2>&1 | head -30
```

Expect HTTP/1.1 404. The response body should NOT contain:

- the literal string `browser-console`
- the literal string `BrowserConsole`
- any `browser-console::*` view path
- the configured `path` value if you customized it

The Laravel framework's default 404 page is acceptable. A custom 404 page is acceptable. The key invariant is no token leak that reveals the route exists.

## Tear down

- `php artisan browser-console:disable` (or remove the package entirely)
- Remove `bcd.php` from `public/` if it was published: `php artisan browser-console:diagnose --remove`
- Rotate the bcrypt password if this was a production smoke
