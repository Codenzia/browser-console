<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces file-based session and cache for console-related requests.
 * Handles both direct console requests and Livewire update calls
 * originating from the console page (detected via Referer header).
 * This ensures the Browser Console works without a database connection.
 */
class ForceFileSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = config('browser-console.path', 'console');
        $isConsole = $request->is($path);
        $isLivewireFromConsole = $request->is('livewire/*')
            && str_contains($request->header('Referer', ''), '/' . $path);

        if ($isConsole || $isLivewireFromConsole) {
            // Optional IP whitelisting — empty/null means allow all
            $allowedIps = config('browser-console.allowed_ips');
            if ($allowedIps) {
                $ips = array_map('trim', explode(',', $allowedIps));
                if (! in_array($request->ip(), $ips, true)) {
                    abort(403, 'Console access denied from this IP.');
                }
            }

            config([
                'session.driver' => 'file',
                'session.cookie' => 'browser-console-session',
                'cache.default' => 'file',
                'browser-console.active' => true,
            ]);

            // Suppress PHP error output to prevent notices/deprecations from
            // corrupting Livewire's JSON response. Errors still reach the log.
            ini_set('display_errors', '0');
        }

        return $next($request);
    }
}
