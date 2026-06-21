<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string defaultState()
 * @method static ?\Carbon\CarbonImmutable enable(?int $ttlMinutes = null, ?string $actor = null)
 * @method static void disable(?string $actor = null)
 * @method static bool isEnabled()
 * @method static ?\Carbon\CarbonImmutable expiresAt()
 * @method static array state()
 *
 * @see \Codenzia\BrowserConsole\Services\ConsoleSwitch
 */
class Console extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'console.switch';
    }
}
