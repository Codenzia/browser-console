# Changelog

All notable changes to `codenzia/browser-console` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.1] - 2026-07-13

### Security
- **`bcd.php` throttle is now concurrency-safe (CON-002 follow-up):** the failed-attempt counter is updated under a single exclusive `flock` held across the whole read-modify-write. The 0.5.0 store used `file_put_contents(LOCK_EX)`, which locks only the write — so racing login attempts could read the same pre-increment count and lose updates, letting a parallel brute-forcer burst past the lockout. Increments are now atomic (one per failure). Stale per-IP records are also pruned on write so the store cannot grow unbounded under a botnet.

### Documentation
- Clarified that the Artisan `denylist` matches the command **name** and does not expand Symfony's unambiguous command abbreviations (`db:wip` → `db:wipe`): it is a guard against accidents and a single leaked password, not a security boundary. For a hard wall, use `read_only` mode (allowlist).

## [0.5.0] - 2026-07-13

### Security
- **Unauthenticated method disclosure closed (CON-001):** the four reference-builder methods (`getCommandReference`, `getShellCommandReference`, `getDeploymentGuide`, `getFilteredCommandReference`) are now `protected`, removing them as client-callable Livewire actions. An anonymous visitor can no longer invoke them directly to disclose the Artisan/seeder inventory or force repeated `Artisan::all()` boots.
- **`bcd.php` brute-force lockout is now IP-keyed and persistent (CON-002):** the failed-attempt counter moved from the attacker-controlled PHP session to an IP-keyed file store (`storage/logs/.bcd-throttle.json`), so dropping the session cookie no longer resets it.
- **Artisan denylist + read-only mode (CON-003):** a configurable `browser-console.artisan.denylist` blocks a small set of irreversible commands (`db:wipe`, `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `key:generate`, `down`) by default — editable/removable, with block messages that name the config to change. New opt-in `BROWSER_CONSOLE_READ_ONLY` restricts Artisan to an allowlist and disables shell + clear/write actions. Full mode remains fully capable (normal deploy commands are unaffected). Destructive commands are filtered from the reference panel in read-only mode (CON-019).
- **`bcd.php` fix-action gate hardened (CON-004):** privileged write/fix actions now require an explicit `BCD_FIX_ENABLED=true` (instead of inferring from `APP_DEBUG`), remain disabled in production and after the 7-day self-expiry, and are additionally force-disabled while a `storage/logs/.bcd-locked` marker is present. `browser-console:disable` writes that marker; `browser-console:enable` and `diagnose --remove` clear it — so the kill switch now also neuters bcd.php. Read-only DB diagnostics redact the DB host/name.
- **`console()` debug helper opt-out (CON-005):** the helper stays enabled everywhere by default (debugging live deployments is a core use case) but can be turned into a safe no-op with `BROWSER_CONSOLE_DEBUG=false`.

### Fixed
- **Artisan prefix stripping (CON-007):** a leading `php artisan `/`artisan ` prefix is now stripped only at position 0, so commands whose arguments contain the literal `artisan ` are no longer truncated.
- **Artisan subprocess resolution (CON-008):** the Artisan tab now spawns `PHP_BINARY` + `base_path('artisan')` with an extended `PATH` (matching shell mode), instead of a bare `php`/`artisan` that a minimal PHP-FPM `PATH` may fail to resolve.
- **Empty log download feedback (CON-010):** downloading when no log file exists now dispatches a browser notice instead of a silent `204`.

### Notes
- Documented (README): shell mode is Linux-only (CON-009); the log viewer exposes secrets verbatim (CON-006).

## [0.4.0] - 2026-07-13

### Security
- **Command-injection RCE fixed (BROWSERCON-1):** artisan and shell commands now execute via an argv array (`proc_open`, no `/bin/sh -c`), so shell metacharacters (`&`, `#`, `|`, globbing, `ext::`, subshells) can no longer chain a second command. The operator denylist additionally rejects a lone `&`, `#`, `(`, `)`, and `!`.
- **Secure-by-default (BROWSERCON-2, breaking):** `BROWSER_CONSOLE_DEFAULT_STATE` now defaults to `local` (was `on`). A hard production guard makes the `/console` route inert in `APP_ENV=production` regardless of configured mode, unless an explicit time-boxed `:enable` unlock is live.
- **Allowlist bypasses closed (BROWSERCON-3):** shell validation now rejects `php artisan tinker`, `find -exec`, `git ext::`, and `--upload-pack`/`--receive-pack`.
- **`public/bcd.php` hardened (BROWSERCON-4):** its privileged write/fix actions (create `.env`, `migrate --force`, chmod/symlink, `.htaccess`) are disabled in production and unless `APP_DEBUG=true`, self-expire 7 days after publish, and every attempt is written to `storage/logs/browser-console-bcd.log`. Read-only diagnostics remain available.
- **Unauthenticated inventory disclosure fixed (BROWSERCON-5):** the Artisan/seeder command reference is built only for authenticated callers and the property is `#[Locked]`.
- **Auth gate consistency (BROWSERCON-6):** `fillCommand()` now requires authentication.

## [0.2.1] - 2026-05-20

### Added
- First tracked release. Early beta. Earlier history not recorded in this changelog — see git log for changes prior to release-tracker adoption.
