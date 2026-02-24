<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAccessCommand extends Command
{
    protected $signature = 'browser-console:create';

    protected $description = 'Create or update the Browser Console credentials (stored in .env)';

    public function handle(): int
    {
        $this->info('');
        $this->info('  Browser Console Access Setup');
        $this->info('  ============================');
        $this->info('');

        $currentUser = config('browser-console.user');
        if ($currentUser) {
            $this->warn("  Current console user: {$currentUser}");
            $this->info('');
        }

        $username = $this->ask('Enter console username');

        if (! $username) {
            $this->error('Username is required.');

            return self::FAILURE;
        }

        $password = $this->secret('Enter console password (min 8 characters)');

        if (! $password || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $confirm = $this->secret('Confirm console password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $hashedPassword = Hash::make($password);

        $this->setEnvValue('BROWSER_CONSOLE_USER', $username);
        $this->setEnvValue('BROWSER_CONSOLE_PASSWORD', $hashedPassword);

        $path = config('browser-console.path', 'console');

        $this->info('');
        $this->info('  Console access credentials saved to .env');
        $this->info("  Username: {$username}");
        $this->info('  Password: (hidden)');
        $this->info('');
        $this->info("  Access the console at: /{$path}");
        $this->info('');

        return self::SUCCESS;
    }

    private function setEnvValue(string $key, string $value): void
    {
        $envFile = app()->environmentFilePath();
        $content = file_get_contents($envFile);

        // Wrap in single quotes to prevent $variable expansion in .env
        $quotedValue = str_contains($value, '$') || str_contains($value, ' ')
            ? "'{$value}'"
            : $value;

        $newLine = "{$key}={$quotedValue}";

        if (preg_match("/^{$key}=.*/m", $content, $matches)) {
            // Use str_replace to avoid $ backreference issues in preg_replace
            $content = str_replace($matches[0], $newLine, $content);
        } else {
            $content = rtrim($content) . "\n{$newLine}\n";
        }

        file_put_contents($envFile, $content);
    }
}
