<?php

declare(strict_types=1);

use Codenzia\BrowserConsole\Support\ConsoleDebug;

if (! function_exists('console')) {
    /**
     * Send debug values to the Browser Console Debug tab (Ray-like utility).
     *
     * Usage:
     *   console('Hello World');
     *   console($user, $request)->label('Auth')->green();
     *   console(['key' => 'value'])->table();
     */
    function console(mixed ...$values): ConsoleDebug
    {
        return new ConsoleDebug(...$values);
    }
}
