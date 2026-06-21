<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Http\Middleware;

use Closure;
use Codenzia\BrowserConsole\Services\ConsoleSwitch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kill-switch gate for console routes.
 *
 * Returns an opaque 404 (not 403) when the kill switch is off, so that
 * disabled installs do not reveal the presence of the /console route to
 * scanners. Runs FIRST in the route group — before ConsoleGate — so neither
 * IP allowlist failures nor the `web` middleware stack execute when the
 * route is sealed.
 */
class ConsoleEnabled
{
    public function __construct(private readonly ConsoleSwitch $switch) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->switch->isEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
