<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $isLivewire = $request->is('livewire/*');
        $hasBody = $isLivewire && str_contains((string) $request->getContent(), 'browser-console');
        $hasCookie = $isLivewire && $request->cookies->has('browser-console-session');
        $hasReferer = $isLivewire && str_contains($request->header('Referer', ''), '/' . $path);
        $isLivewireFromConsole = $isLivewire && ($hasBody || $hasCookie || $hasReferer);

        // Debug logging — only when APP_DEBUG is true
        if (config('app.debug') && ($isConsole || $isLivewire)) {
            Log::debug('[BrowserConsole] ForceFileSession', [
                'uri' => $request->getRequestUri(),
                'method' => $request->method(),
                'isConsole' => $isConsole,
                'isLivewire' => $isLivewire,
                'isLivewireFromConsole' => $isLivewireFromConsole,
                'detection' => $isLivewire ? [
                    'body' => $hasBody,
                    'cookie' => $hasCookie,
                    'referer' => $hasReferer,
                ] : 'N/A',
                'cookies' => array_keys($request->cookies->all()),
                'referer' => $request->header('Referer', '(none)'),
                'session.driver' => config('session.driver'),
                'session.cookie' => config('session.cookie'),
            ]);
        }

        if ($isConsole || $isLivewireFromConsole) {
            // Optional IP whitelisting — empty/null means allow all
            $allowedIps = config('browser-console.allowed_ips');
            if ($allowedIps) {
                $ips = array_map('trim', explode(',', $allowedIps));
                if (! in_array($request->ip(), $ips, true)) {
                    abort(403, 'Console access denied from this IP.');
                }
            }

            // Ensure the file session storage directory exists (common issue
            // on fresh VPS deployments where only the database driver was used).
            $sessionPath = storage_path('framework/sessions');
            if (! is_dir($sessionPath)) {
                @mkdir($sessionPath, 0755, true);
            }

            config([
                'session.driver' => 'file',
                'session.files' => $sessionPath,
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
