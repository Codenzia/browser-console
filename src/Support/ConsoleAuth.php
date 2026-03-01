<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Cookie-based authentication for the browser console.
 *
 * Stores auth state in an encrypted cookie (`browser-console-auth`) instead
 * of Laravel sessions. This avoids any interference with the host app's
 * session driver, cookies, or middleware stack.
 *
 * The cookie is automatically encrypted/decrypted by Laravel's EncryptCookies
 * middleware (part of the web group).
 */
class ConsoleAuth
{
    private const COOKIE_NAME = 'browser-console-auth';

    /**
     * Check if the current request is authenticated.
     *
     * Reads the cookie, validates the auth flag, and checks for session timeout.
     * Also checks in-memory state set by login()/logout() during the current request.
     */
    public static function check(Request $request): bool
    {
        // If login() or logout() was called during this request, use that state.
        // Stored in the app container so it resets between HTTP requests (and tests).
        if (app()->bound('browser-console.auth.pending')) {
            return (bool) app('browser-console.auth.pending');
        }

        $payload = static::readCookie($request);

        if (! $payload || empty($payload['authenticated'])) {
            return false;
        }

        $timeout = (int) config('browser-console.session_timeout', 1800);
        $lastActivity = $payload['last_activity'] ?? 0;

        if ($lastActivity && (time() - $lastActivity) > $timeout) {
            static::logout();

            return false;
        }

        // Refresh last_activity on every authenticated request
        static::queueCookie(true);

        return true;
    }

    /**
     * Queue an authenticated cookie (call after successful login).
     */
    public static function login(): void
    {
        app()->instance('browser-console.auth.pending', true);
        static::queueCookie(true);
    }

    /**
     * Queue a cookie forget (call on logout).
     */
    public static function logout(): void
    {
        app()->instance('browser-console.auth.pending', false);
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }

    /**
     * Read and decode the auth cookie from the request.
     *
     * @return array{authenticated: bool, last_activity: int}|null
     */
    private static function readCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE_NAME);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * Queue an auth cookie with the given state.
     */
    private static function queueCookie(bool $authenticated): void
    {
        $payload = json_encode([
            'authenticated' => $authenticated,
            'last_activity' => time(),
        ]);

        $timeout = (int) config('browser-console.session_timeout', 1800);

        Cookie::queue(
            self::COOKIE_NAME,
            $payload,
            (int) ceil($timeout / 60), // minutes
            '/',
            null,    // domain — auto
            null,    // secure — auto
            true,    // httpOnly
            false,   // raw
            'Lax',   // sameSite
        );
    }
}
