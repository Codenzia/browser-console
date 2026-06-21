<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Codenzia\BrowserConsole\Services\ConsoleSwitch;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'browser-console:status';

    protected $description = 'Show the current state of the Browser Console kill switch and basic config health.';

    public function handle(ConsoleSwitch $switch): int
    {
        $state = $switch->state();

        $expiresAt = $state['expires_at'];
        $remainingSeconds = $state['remaining_seconds'];

        $this->newLine();
        $this->line('  default_state:       '.$state['default_state']);
        $this->line('  effective_state:     '.$state['effective_state']);
        $this->line('  expires_at:          '.($expiresAt ?? '—'));
        $this->line('  remaining:           '.($remainingSeconds !== null ? $this->humanizeSeconds($remainingSeconds) : '—'));
        $this->line('  password_configured: '.($state['password_configured'] ? 'yes' : 'no'));
        $this->newLine();

        if (! $state['password_configured']) {
            $this->warn('  Password is not configured. The route is locked regardless of the kill switch.');
            $this->line('  Run: php artisan browser-console:create');
            $this->newLine();
        }

        return self::SUCCESS;
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
