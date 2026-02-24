<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ShowAccessCommand extends Command
{
    protected $signature = 'browser-console:show {--verify : Verify the password against the stored hash}';

    protected $description = 'Show the current Browser Console username (use --verify to test password)';

    public function handle(): int
    {
        $username = config('browser-console.user');
        $passwordHash = config('browser-console.password');

        if (! $username || ! $passwordHash) {
            $this->warn('No console access configured.');
            $this->info('Run: php artisan browser-console:create');

            return self::SUCCESS;
        }

        $path = config('browser-console.path', 'console');

        $this->info('');
        $this->info("  Console username: {$username}");
        $this->info("  Console URL: /{$path}");
        $this->info('');

        if ($this->option('verify')) {
            $password = $this->secret('Enter password to verify');

            if (Hash::check($password, $passwordHash)) {
                $this->info('  Password is correct.');
            } else {
                $this->error('  Password is incorrect.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
