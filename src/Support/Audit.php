<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Support;

use Codenzia\BrowserConsole\Events\ConsoleAudit;
use Illuminate\Support\Facades\Log;
use Throwable;

class Audit
{
    /**
     * Emit a structured audit record.
     *
     * Writes to the configured log channel (when audit.enabled is true) and
     * dispatches a ConsoleAudit event so host apps can subscribe.
     *
     * Sensitive bits (password attempts, command output bodies) MUST NOT be
     * included in $context by callers.
     *
     * @param  array<string, mixed>  $context
     */
    public static function event(string $name, array $context = []): void
    {
        if (! (bool) config('browser-console.audit.enabled', true)) {
            return;
        }

        $context = self::scrub($context);

        // Best-effort log write — never let a misconfigured channel break the
        // console flow. The event dispatch below still fires either way.
        try {
            $channel = (string) config('browser-console.audit.channel', 'stack');
            Log::channel($channel)->info($name, $context);
        } catch (Throwable) {
            // Swallow log errors to keep the console flow resilient.
        }

        try {
            ConsoleAudit::dispatch($name, $context);
        } catch (Throwable) {
            // Dispatching can throw if the host has no event dispatcher
            // configured (e.g., minimal CLI bootstrap). Don't break callers.
        }
    }

    /**
     * Defensive filter: strip keys that should never appear in audit context
     * even if a caller passes them by mistake.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function scrub(array $context): array
    {
        $forbidden = ['password', 'password_confirmation', 'output', 'output_body', 'secret'];

        foreach ($forbidden as $key) {
            unset($context[$key]);
        }

        return $context;
    }
}
