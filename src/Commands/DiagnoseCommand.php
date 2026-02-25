<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Illuminate\Console\Command;

class DiagnoseCommand extends Command
{
    protected $signature = 'browser-console:diagnose
        {--refresh : Force-refresh the diagnostics page in public/bcd.php}
        {--remove : Remove the diagnostics page from public/}';

    protected $description = 'Run deployment diagnostics or manage the web diagnostics page';

    public function handle(): int
    {
        if ($this->option('refresh')) {
            return $this->refreshDiagnostics();
        }

        if ($this->option('remove')) {
            return $this->removeDiagnostics();
        }

        return $this->runDiagnostics();
    }

    private function refreshDiagnostics(): int
    {
        $source = dirname(__DIR__, 2) . '/stubs/bcd.php';
        $destination = public_path('bcd.php');

        if (! file_exists($source)) {
            $this->error('Diagnostics stub not found in package.');

            return self::FAILURE;
        }

        copy($source, $destination);

        $this->info('');
        $this->info('  Diagnostics page refreshed.');
        $this->info("  Access at: {$this->getLaravel()->make('url')->to('bcd.php')}");
        $this->info('');

        return self::SUCCESS;
    }

    private function removeDiagnostics(): int
    {
        $file = public_path('bcd.php');

        if (! file_exists($file)) {
            $this->info('  Diagnostics page not found — nothing to remove.');

            return self::SUCCESS;
        }

        unlink($file);
        $this->info('  Diagnostics page removed from public/.');

        return self::SUCCESS;
    }

    private function runDiagnostics(): int
    {
        $this->info('');
        $this->info('  Browser Console — Deployment Diagnostics');
        $this->info('  =========================================');
        $this->info('');

        $fails = 0;

        // PHP checks
        $this->sectionHeader('PHP Environment');
        $fails += $this->check('PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);
        $fails += $this->check('proc_open()', function_exists('proc_open'), function_exists('proc_open') ? 'Available' : 'DISABLED');

        $requiredExts = ['json', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'dom', 'curl', 'pdo'];
        foreach ($requiredExts as $ext) {
            $fails += $this->check("ext-{$ext}", extension_loaded($ext));
        }

        // Laravel structure
        $this->sectionHeader('Laravel Structure');
        $basePath = base_path();

        $fails += $this->check('.env', file_exists($basePath . '/.env'));

        if (file_exists($basePath . '/.env')) {
            $envContent = file_get_contents($basePath . '/.env');
            $fails += $this->check('APP_KEY', (bool) preg_match('/^APP_KEY=base64:.+$/m', $envContent));
        }

        $writables = [
            'storage',
            'storage/app',
            'storage/app/public',
            'storage/framework',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache',
            'public',
        ];

        foreach ($writables as $dir) {
            $full = $basePath . '/' . $dir;
            $exists = is_dir($full);
            $writable = $exists && is_writable($full);
            $fails += $this->check($dir, $writable, $writable ? 'Writable' : ($exists ? 'NOT WRITABLE' : 'MISSING'));
        }

        $configCached = file_exists($basePath . '/bootstrap/cache/config.php');
        $this->check('Config Cache', true, $configCached ? 'Cached' : 'Not cached');

        // Browser Console
        $this->sectionHeader('Browser Console');

        $fails += $this->check('Package installed', class_exists(\Codenzia\BrowserConsole\BrowserConsoleServiceProvider::class));

        $configPublished = file_exists($basePath . '/config/browser-console.php');
        $fails += $this->check('Config published', $configPublished);

        $hasUser = ! empty(config('browser-console.user'));
        $fails += $this->check('BROWSER_CONSOLE_USER', $hasUser, $hasUser ? 'Set' : 'NOT SET');

        $hasPass = ! empty(config('browser-console.password'));
        $fails += $this->check('BROWSER_CONSOLE_PASSWORD', $hasPass, $hasPass ? 'Set' : 'NOT SET');

        $lwInstalled = class_exists(\Livewire\Livewire::class);
        $fails += $this->check('Livewire installed', $lwInstalled);

        // Route check
        $consolePath = config('browser-console.path', 'console');
        try {
            $routes = app('router')->getRoutes();
            $testRequest = \Illuminate\Http\Request::create('/' . $consolePath, 'GET');
            $matched = $routes->match($testRequest);
            $this->check("Route /{$consolePath}", (bool) $matched, 'Registered');
        } catch (\Throwable) {
            $fails += $this->check("Route /{$consolePath}", false, 'NOT REGISTERED');
        }

        // Summary
        $this->info('');
        if ($fails === 0) {
            $this->info('  <fg=green>All checks passed.</>');
        } else {
            $this->warn("  {$fails} issue(s) found.");
        }

        $diagFile = public_path('bcd.php');
        if (file_exists($diagFile)) {
            $this->info('');
            $this->warn('  Note: public/bcd.php exists. Remove when done:');
            $this->warn('  php artisan browser-console:diagnose --remove');
        }

        $this->info('');

        return $fails === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function sectionHeader(string $title): void
    {
        $this->info('');
        $this->info("  {$title}");
        $this->info('  ' . str_repeat('-', strlen($title)));
    }

    /**
     * Print a single check result and return 1 if it failed, 0 if passed.
     */
    private function check(string $label, bool $pass, string $detail = ''): int
    {
        $icon = $pass ? '<fg=green>&#10004;</>' : '<fg=red>&#10008;</>';
        $detailStr = $detail ? " <fg=gray>({$detail})</>" : '';
        $line = "    {$icon} {$label}{$detailStr}";
        $this->line($line);

        return $pass ? 0 : 1;
    }
}
