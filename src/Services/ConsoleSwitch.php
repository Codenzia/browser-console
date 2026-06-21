<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Services;

use Carbon\CarbonImmutable;
use Codenzia\BrowserConsole\Support\Audit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Runtime kill switch for the browser console route.
 *
 * State lives in cache under a single key (default `browser-console:state`)
 * holding one of:
 *   - absent  → behavior governed by killswitch.default_state config
 *   - ['kind' => 'enabled_until', 'expires_at' => <ISO 8601>]
 *       written only in off/local mode by enable(); self-evicts via cache TTL
 *   - ['kind' => 'force_disabled']
 *       written only in on mode by disable(); persists until enable() clears
 *
 * isEnabled() also requires the configured password to be non-empty as a
 * defense-in-depth check — an enabled-but-misconfigured console still fails
 * closed at the route layer.
 */
class ConsoleSwitch
{
    private const VALID_MODES = ['on', 'off', 'local'];

    private const STATE_ENABLED_UNTIL = 'enabled_until';

    private const STATE_FORCE_DISABLED = 'force_disabled';

    /** Per-process latch so we only emit the "invalid mode" warning once. */
    private bool $invalidModeWarned = false;

    /** Per-process latch so we only emit `console.switch.expired` once per expiry. */
    private bool $expiryEmitted = false;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Application $app,
    ) {}

    /**
     * Resolve the effective default state to 'on' or 'off'.
     *
     * Collapses 'local' via app environment. Invalid values fall back to 'on'
     * and log a one-shot warning to the audit channel.
     */
    public function defaultState(): string
    {
        $raw = config('browser-console.killswitch.default_state', 'on');
        $value = is_string($raw) ? strtolower(trim($raw)) : 'on';

        if (! in_array($value, self::VALID_MODES, true)) {
            $this->warnInvalidMode($raw);

            return 'on';
        }

        if ($value === 'local') {
            return $this->app->environment('local') ? 'on' : 'off';
        }

        return $value;
    }

    /**
     * Enable the route.
     *
     * In off/local strict mode requires a TTL in 1..max_ttl and writes a
     * self-expiring 'enabled_until' entry. Returns the new expiry.
     *
     * In on mode ignores the TTL (always-on by default) and simply clears any
     * prior force_disabled lockout. Returns null.
     *
     * @throws InvalidArgumentException when TTL is out of range in strict mode
     */
    public function enable(?int $ttlMinutes = null, ?string $actor = null): ?CarbonImmutable
    {
        $mode = $this->defaultState();

        if ($mode === 'on') {
            // Clear any prior force_disabled lockout.
            $this->cache->forget($this->cacheKey());

            Audit::event('console.switch.enabled', [
                'mode' => 'on',
                'actor' => $actor,
                'ttl_minutes' => null,
                'expires_at' => null,
            ]);

            return null;
        }

        // Strict mode — TTL is mandatory.
        $ttl = $ttlMinutes ?? (int) config('browser-console.killswitch.default_ttl', 10);
        $max = max(1, (int) config('browser-console.killswitch.max_ttl', 60));

        if ($ttl < 1 || $ttl > $max) {
            throw new InvalidArgumentException(
                "TTL must be between 1 and {$max} minutes (got {$ttl})."
            );
        }

        $expiresAt = CarbonImmutable::now()->addMinutes($ttl);

        $this->cache->put(
            $this->cacheKey(),
            [
                'kind' => self::STATE_ENABLED_UNTIL,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            $ttl * 60,
        );

        // Reset the expiry-emitted latch so a fresh expiry can fire its event.
        $this->expiryEmitted = false;

        Audit::event('console.switch.enabled', [
            'mode' => $mode,
            'actor' => $actor,
            'ttl_minutes' => $ttl,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return $expiresAt;
    }

    /**
     * Disable the route.
     *
     * In off/local strict mode clears any 'enabled_until' entry (idempotent).
     * In on mode writes a 'force_disabled' marker that persists until enable()
     * is called.
     */
    public function disable(?string $actor = null): void
    {
        $mode = $this->defaultState();

        if ($mode === 'on') {
            $this->cache->forever($this->cacheKey(), [
                'kind' => self::STATE_FORCE_DISABLED,
            ]);
        } else {
            $this->cache->forget($this->cacheKey());
        }

        Audit::event('console.switch.disabled', [
            'mode' => $mode,
            'actor' => $actor,
        ]);
    }

    /**
     * Whether the /console route should currently be reachable.
     *
     * Truth table — always also requires password config to be non-empty:
     *
     *   cache state           | default=off | default=on | default=local local | default=local other
     *   ----------------------|------------|-----------|--------------------|-------------------
     *   absent                |   false    |   true    |        true        |       false
     *   enabled_until > now   |   true     |    n/a*   |        true        |       true
     *   enabled_until <= now  |   false    |    n/a*   |       false        |       false
     *   force_disabled        |    n/a*    |   false   |        n/a*        |       n/a*
     *
     * *unreachable combinations: enable()/disable() only write the kind that
     *  matches the current mode.
     */
    public function isEnabled(): bool
    {
        if (! $this->passwordConfigured()) {
            return false;
        }

        $entry = $this->cache->get($this->cacheKey());
        $mode = $this->defaultState();

        if (! is_array($entry) || ! isset($entry['kind'])) {
            return $mode === 'on';
        }

        return match ($entry['kind']) {
            self::STATE_ENABLED_UNTIL => $this->isEnabledUntilLive($entry),
            self::STATE_FORCE_DISABLED => false,
            default => $mode === 'on',
        };
    }

    /**
     * Expiry timestamp for the current 'enabled_until' entry, or null.
     */
    public function expiresAt(): ?CarbonImmutable
    {
        $entry = $this->cache->get($this->cacheKey());

        if (! is_array($entry) || ($entry['kind'] ?? null) !== self::STATE_ENABLED_UNTIL) {
            return null;
        }

        $raw = $entry['expires_at'] ?? null;

        if (! is_string($raw)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Public state snapshot for the :status command and the host app.
     *
     * @return array{
     *   default_state: string,
     *   effective_state: string,
     *   enabled: bool,
     *   expires_at: ?string,
     *   remaining_seconds: ?int,
     *   password_configured: bool
     * }
     */
    public function state(): array
    {
        $expiresAt = $this->expiresAt();
        $remaining = null;

        if ($expiresAt !== null) {
            $remaining = max(0, $expiresAt->getTimestamp() - time());
        }

        return [
            'default_state' => $this->defaultState(),
            'effective_state' => $this->isEnabled() ? 'enabled' : 'disabled',
            'enabled' => $this->isEnabled(),
            'expires_at' => $expiresAt?->toIso8601String(),
            'remaining_seconds' => $remaining,
            'password_configured' => $this->passwordConfigured(),
        ];
    }

    private function isEnabledUntilLive(array $entry): bool
    {
        $raw = $entry['expires_at'] ?? null;

        if (! is_string($raw)) {
            return false;
        }

        try {
            $expiresAt = CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return false;
        }

        if ($expiresAt->isFuture()) {
            return true;
        }

        // Expired — emit a one-shot audit event then clear the stale entry.
        if (! $this->expiryEmitted) {
            $this->expiryEmitted = true;
            Audit::event('console.switch.expired', [
                'expired_at' => $expiresAt->toIso8601String(),
            ]);
        }

        $this->cache->forget($this->cacheKey());

        return false;
    }

    private function passwordConfigured(): bool
    {
        $password = config('browser-console.password');

        return is_string($password) && $password !== '';
    }

    private function cacheKey(): string
    {
        $key = config('browser-console.killswitch.cache_key', 'browser-console:state');

        return is_string($key) && $key !== '' ? $key : 'browser-console:state';
    }

    private function warnInvalidMode(mixed $raw): void
    {
        if ($this->invalidModeWarned) {
            return;
        }
        $this->invalidModeWarned = true;

        try {
            $channel = (string) config('browser-console.audit.channel', 'stack');
            Log::channel($channel)->warning(
                'browser-console: invalid killswitch.default_state value; falling back to "on".',
                ['received' => is_scalar($raw) ? (string) $raw : gettype($raw)],
            );
        } catch (\Throwable) {
            // Best-effort warning.
        }
    }
}
