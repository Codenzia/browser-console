<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Codenzia\BrowserConsole\Services\ConsoleSwitch;
use Illuminate\Console\Command;
use InvalidArgumentException;

class EnableCommand extends Command
{
    protected $signature = 'browser-console:enable
        {--ttl= : Minutes the route stays reachable before auto-locking (off/local mode only; 1..max_ttl).}
        {--actor= : Identifier of the operator running this command, recorded in the audit log.}';

    protected $description = 'Enable the Browser Console route (TTL required in off/local mode, ignored in on mode).';

    public function handle(ConsoleSwitch $switch): int
    {
        $mode = $switch->defaultState();
        $actor = $this->resolveActor();

        try {
            $ttl = $this->resolveTtl();
            $expiresAt = $switch->enable($ttl, $actor);
        } catch (InvalidArgumentException $e) {
            $this->error('  '.$e->getMessage());
            $this->line('  Set --ttl between 1 and the configured browser-console.killswitch.max_ttl.');

            return self::FAILURE;
        }

        $this->newLine();

        if ($mode === 'on') {
            $this->info('  Browser Console kill switch: cleared.');
            $this->line('  Default state is "on" — the /console route is reachable.');
        } else {
            $this->info('  Browser Console enabled.');
            $this->line('  Mode: '.$mode);
            $this->line('  Expires at: '.($expiresAt?->toDateTimeString() ?? '—'));
            if ($expiresAt !== null) {
                $remaining = max(0, $expiresAt->getTimestamp() - time());
                $this->line('  Remaining: '.$this->humanizeSeconds($remaining));
            }
        }

        if ($actor !== null) {
            $this->line('  Actor: '.$actor);
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function resolveTtl(): ?int
    {
        $raw = $this->option('ttl');

        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            throw new InvalidArgumentException('--ttl must be an integer number of minutes.');
        }

        return (int) $raw;
    }

    private function resolveActor(): ?string
    {
        $raw = $this->option('actor');

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        // Best-effort fallback for the CLI user when --actor is omitted.
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            if (is_array($info) && isset($info['name']) && is_string($info['name'])) {
                return 'cli:'.$info['name'];
            }
        }

        $env = getenv('USER') ?: getenv('USERNAME');

        return is_string($env) && $env !== '' ? 'cli:'.$env : null;
    }

    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return $remainder === 0 ? $minutes.'m' : $minutes.'m '.$remainder.'s';
    }
}
