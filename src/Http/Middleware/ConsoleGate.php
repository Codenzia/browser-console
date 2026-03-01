<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate middleware for console routes.
 *
 * Applied only to console routes (and their Livewire updates via
 * PersistentMiddleware). Handles IP whitelisting and marks the
 * request as console-related. Does NOT touch sessions, cookies,
 * or cache — zero impact on the host application.
 */
class ConsoleGate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Optional IP whitelisting — empty/null means allow all
        $allowedIps = config('browser-console.allowed_ips');
        if ($allowedIps) {
            $ips = array_map('trim', explode(',', $allowedIps));
            if (! in_array($request->ip(), $ips, true)) {
                abort(403, 'Console access denied from this IP.');
            }
        }

        // Mark this request as console-related for any package code that checks
        config(['browser-console.active' => true]);

        // Suppress PHP error output to prevent notices/deprecations from
        // corrupting Livewire's JSON response. Errors still reach the log.
        ini_set('display_errors', '0');

        return $next($request);
    }
}
