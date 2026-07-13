<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Codenzia\BrowserConsole\Services\ConsoleSwitch;
use Illuminate\Console\Command;

class DisableCommand extends Command
{
    protected $signature = 'browser-console:disable
        {--actor= : Identifier of the operator running this command, recorded in the audit log.}';

    protected $description = 'Disable the Browser Console route. In on mode this writes a sticky lockout; in off/local mode it clears any active enable.';

    public function handle(ConsoleSwitch $switch): int
    {
        $mode = $switch->defaultState();
        $actor = $this->resolveActor();

        $switch->disable($actor);

        // Neuter public/bcd.php's privileged fix actions too. bcd.php is not
        // governed by the cache-backed kill switch, so we drop a marker file it
        // reads on every request to force its write/fix actions off until
        // browser-console:enable clears it.
        $this->lockBcdFixActions();

        $this->newLine();

        if ($mode === 'on') {
            $this->info('  Browser Console locked.');
            $this->line('  The /console route now returns 404. Run `php artisan browser-console:enable` to restore.');
        } else {
            $this->info('  Browser Console disabled.');
            $this->line('  Default state is "'.$mode.'" — the route was already 404 unless explicitly enabled. Any active TTL is now cleared.');
        }

        if ($actor !== null) {
            $this->line('  Actor: '.$actor);
        }

        // bcd.php is a separate privileged surface that is NOT governed by the
        // kill switch — disabling the console does not retire it. Warn loudly.
        if (file_exists(public_path('bcd.php'))) {
            $this->newLine();
            $this->warn('  Warning: public/bcd.php still exists and is NOT covered by the kill switch.');
            $this->warn('  It remains a privileged web endpoint. Remove it with:');
            $this->warn('  php artisan browser-console:diagnose --remove');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Write the kill-switch marker that forces public/bcd.php's fix actions off.
     */
    private function lockBcdFixActions(): void
    {
        $dir = storage_path('logs');

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $dir.'/.bcd-locked',
            'Locked by browser-console:disable at '.now()->toIso8601String()."\n",
            LOCK_EX,
        );
    }

    private function resolveActor(): ?string
    {
        $raw = $this->option('actor');

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            if (is_array($info) && isset($info['name']) && is_string($info['name'])) {
                return 'cli:'.$info['name'];
            }
        }

        $env = getenv('USER') ?: getenv('USERNAME');

        return is_string($env) && $env !== '' ? 'cli:'.$env : null;
    }
}
