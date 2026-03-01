<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Commands;

use Codenzia\BrowserConsole\Http\Middleware\ForceFileSession;
use Illuminate\Console\Command;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

class DiagnoseCommand extends Command
{
    protected $signature = 'browser-console:diagnose
        {--refresh : Force-refresh the diagnostics page in public/bcd.php}
        {--remove : Remove the diagnostics page from public/}
        {--fix : Attempt to auto-fix detected issues}';

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
        $basePath = base_path();

        // PHP Environment
        $this->sectionHeader('PHP Environment');
        $fails += $this->check('PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);
        $fails += $this->check('proc_open()', function_exists('proc_open'), function_exists('proc_open') ? 'Available' : 'DISABLED');
        $fails += $this->check('symlink()', function_exists('symlink'), function_exists('symlink') ? 'Available' : 'DISABLED');

        $requiredExts = ['json', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'dom', 'curl', 'pdo'];
        foreach ($requiredExts as $ext) {
            $fails += $this->check("ext-{$ext}", extension_loaded($ext));
        }

        $opcacheLoaded = extension_loaded('Zend OPcache');
        $this->check('OPcache', true, $opcacheLoaded ? (ini_get('opcache.enable') ? 'Enabled' : 'Loaded, disabled') : 'Not loaded');

        // Laravel Structure & Environment
        $this->sectionHeader('Laravel Structure');

        $fails += $this->check('.env', file_exists($basePath . '/.env'));
        $this->check('.env.example', file_exists($basePath . '/.env.example'), file_exists($basePath . '/.env.example') ? 'Found' : 'MISSING');

        if (file_exists($basePath . '/.env')) {
            $envContent = file_get_contents($basePath . '/.env');
            $fails += $this->check('APP_KEY', (bool) preg_match('/^APP_KEY=base64:.+$/m', $envContent));

            $appEnv = 'unknown';
            if (preg_match('/^APP_ENV=(.+)$/m', $envContent, $m)) {
                $appEnv = trim($m[1]);
            }
            $this->check('APP_ENV', true, $appEnv);

            $appDebug = (bool) preg_match('/^APP_DEBUG=true$/mi', $envContent);
            $isProduction = $appEnv === 'production';
            $fails += $this->check('APP_DEBUG', ! ($isProduction && $appDebug), $appDebug ? 'true' . ($isProduction ? ' (RISK in production)' : '') : 'false');

            if (preg_match('/^APP_URL=(.+)$/m', $envContent, $m)) {
                $appUrl = trim($m[1]);
                $isLocalhost = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');
                $fails += $this->check('APP_URL', ! ($isProduction && $isLocalhost), $appUrl);
            }
        }

        $fails += $this->check('vendor/', is_dir($basePath . '/vendor'));
        $fails += $this->check('vendor/autoload.php', file_exists($basePath . '/vendor/autoload.php'));
        $this->check('composer.lock', file_exists($basePath . '/composer.lock'), file_exists($basePath . '/composer.lock') ? 'Found' : 'MISSING');

        $configCached = file_exists($basePath . '/bootstrap/cache/config.php');
        $this->check('Config Cache', true, $configCached ? 'Cached' : 'Not cached');

        $routesCached = file_exists($basePath . '/bootstrap/cache/routes-v7.php');
        $this->check('Routes Cache', true, $routesCached ? 'Cached' : 'Not cached');

        // Web Server & Public Directory
        $this->sectionHeader('Web Server & Public');

        $fails += $this->check('public/index.php', file_exists($basePath . '/public/index.php'));

        $htaccessPath = $basePath . '/public/.htaccess';
        if (file_exists($htaccessPath)) {
            $htContent = file_get_contents($htaccessPath);
            $hasRewrite = (bool) preg_match('/RewriteEngine\s+On/i', $htContent);
            $hasRule = str_contains($htContent, 'index.php');
            $fails += $this->check('.htaccess', $hasRewrite && $hasRule, ($hasRewrite && $hasRule) ? 'Valid' : 'Missing rewrite rules');
        } else {
            $fails += $this->check('.htaccess', false, 'MISSING');
        }

        $hotExists = file_exists($basePath . '/public/hot');
        if ($hotExists && $this->option('fix')) {
            @unlink($basePath . '/public/hot');
            $hotExists = file_exists($basePath . '/public/hot');
            if (! $hotExists) {
                $this->fixed('Removed public/hot');
            }
        }
        $fails += $this->check('public/hot', ! $hotExists, $hotExists ? 'EXISTS (Vite dev leftover)' : 'Not present');

        if (file_exists($basePath . '/package.json')) {
            $hasBuild = is_dir($basePath . '/public/build');
            $hasManifest = file_exists($basePath . '/public/build/manifest.json');
            if ($hasBuild && $hasManifest) {
                $this->check('public/build/', true, 'Found with manifest');
            } elseif ($hasBuild) {
                $fails += $this->check('public/build/manifest.json', false, 'MISSING');
            } else {
                $fails += $this->check('public/build/', false, 'MISSING (run npm run build)');
            }
        }

        $publicStorage = $basePath . '/public/storage';
        $this->check('public/storage symlink', true, is_link($publicStorage) ? 'Linked' : 'Not created (optional)');

        // File & Directory Permissions
        $this->sectionHeader('Permissions');

        $writables = [
            'storage', 'storage/app', 'storage/app/public',
            'storage/framework', 'storage/framework/sessions',
            'storage/framework/views', 'storage/framework/cache',
            'storage/logs', 'bootstrap/cache',
        ];

        foreach ($writables as $dir) {
            $full = $basePath . '/' . $dir;
            $exists = is_dir($full);
            $writable = $exists && is_writable($full);

            if (! $writable && $this->option('fix')) {
                if (! $exists) {
                    @mkdir($full, 0775, true);
                } else {
                    @chmod($full, 0775);
                }
                $exists = is_dir($full);
                $writable = $exists && is_writable($full);
                if ($writable) {
                    $this->fixed($dir);
                }
            }

            $fails += $this->check($dir, $writable, $writable ? 'Writable' : ($exists ? 'NOT WRITABLE' : 'MISSING'));
        }

        $this->check('public/ (readable)', is_readable($basePath . '/public'), is_readable($basePath . '/public') ? 'Readable' : 'NOT READABLE');

        // Browser Console Package
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

        // Session & CSRF
        $fails += $this->runSessionDiagnostics();

        // Auto-fix: clear caches & publish bcd.php
        if ($this->option('fix')) {
            $this->sectionHeader('Auto-Fix Actions');

            // Clear all Laravel caches
            foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear'] as $cmd) {
                try {
                    \Illuminate\Support\Facades\Artisan::call($cmd);
                    $this->fixed($cmd);
                } catch (\Throwable) {
                    // Ignore — some commands may not exist in older Laravel
                }
            }

            // Publish bcd.php if missing
            $diagFile = public_path('bcd.php');
            if (! file_exists($diagFile)) {
                $source = dirname(__DIR__, 2) . '/stubs/bcd.php';
                if (file_exists($source)) {
                    copy($source, $diagFile);
                    $this->fixed('Published public/bcd.php');
                }
            }
        }

        // Summary
        $this->info('');
        if ($fails === 0) {
            $this->info('  <fg=green>All checks passed.</>');
        } else {
            $this->warn("  {$fails} issue(s) found.");
            if (! $this->option('fix')) {
                $this->info('  Run with <fg=yellow>--fix</> to attempt auto-repair.');
            }
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

    private function runSessionDiagnostics(): int
    {
        $fails = 0;

        // ── Session Configuration ──
        $this->sectionHeader('Session & CSRF');

        $driver = config('session.driver', 'file');
        $this->check('SESSION_DRIVER', true, $driver);

        $cookie = config('session.cookie', 'laravel_session');
        $this->check('Session cookie name', true, $cookie);

        $domain = config('session.domain') ?: '(auto)';
        $this->check('Session domain', true, $domain);

        $secure = config('session.secure');
        $secureLabel = $secure ? 'true' : 'false';
        // Warn if secure=true — could block cookies behind a reverse proxy
        if ($secure) {
            $secureLabel .= ' — cookies only sent over HTTPS';
        }
        $this->check('Session secure cookie', true, $secureLabel);

        $sameSite = config('session.same_site', 'lax');
        $this->check('Session same_site', true, (string) $sameSite);

        $encrypt = config('session.encrypt', false);
        $this->check('Session encrypt', true, $encrypt ? 'true' : 'false');

        // ── Middleware Stack ──
        $this->sectionHeader('Middleware Stack');

        // Check ForceFileSession in global middleware (HTTP Kernel)
        $globalMiddleware = [];
        $fsInGlobal = false;
        try {
            /** @var \Illuminate\Foundation\Http\Kernel $kernel */
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            if (method_exists($kernel, 'getGlobalMiddleware')) {
                $globalMiddleware = $kernel->getGlobalMiddleware();
            }
            $fsInGlobal = in_array(ForceFileSession::class, $globalMiddleware, true);
        } catch (\Throwable) {
            // CLI — kernel may not be available; replay boot logic
        }

        // In CLI mode the ServiceProvider's packageBooted() may not have
        // registered ForceFileSession with the Kernel. Check and simulate.
        if (! $fsInGlobal && ! empty($globalMiddleware)) {
            // ServiceProvider would call $kernel->prependMiddleware()
            $fsInGlobal = false; // truly missing
        } elseif (empty($globalMiddleware)) {
            // Kernel not available — replay: assume it would be registered
            $fsInGlobal = class_exists(ForceFileSession::class);
        }

        $fails += $this->check(
            'ForceFileSession in global middleware',
            $fsInGlobal,
            $fsInGlobal ? 'Registered' : 'MISSING — Livewire POSTs will fail with 419'
        );

        // Check the web middleware group for StartSession & VerifyCsrfToken
        /** @var Router $router */
        $router = app(Router::class);
        $webGroup = $router->getMiddlewareGroups()['web'] ?? [];

        if (empty($webGroup)) {
            try {
                app(\Illuminate\Contracts\Http\Kernel::class);
                $webGroup = $router->getMiddlewareGroups()['web'] ?? [];
            } catch (\Throwable) {
                // Ignore
            }
        }

        $ssInWeb = false;
        $csrfInWeb = false;
        foreach ($webGroup as $mw) {
            if (is_string($mw) && ($mw === StartSession::class || is_subclass_of($mw, StartSession::class) || str_contains($mw, 'StartSession'))) {
                $ssInWeb = true;
            }
            if (is_string($mw) && ($mw === VerifyCsrfToken::class || is_subclass_of($mw, VerifyCsrfToken::class) || str_contains($mw, 'CsrfToken'))) {
                $csrfInWeb = true;
            }
        }
        $fails += $this->check('StartSession in web group', $ssInWeb);
        $fails += $this->check('VerifyCsrfToken in web group', $csrfInWeb);

        // Show global + web middleware for inspection
        if (! empty($globalMiddleware)) {
            $this->info('');
            $this->info('    Global middleware:');
            foreach ($globalMiddleware as $i => $mw) {
                $short = is_string($mw) ? class_basename($mw) : '(closure)';
                $this->line("      [{$i}] {$short}");
            }
        }

        $this->info('');
        $this->info('    Web middleware group:');
        foreach ($webGroup as $i => $mw) {
            $short = is_string($mw) ? class_basename($mw) : '(closure)';
            $this->line("      [{$i}] {$short}");
        }

        // ── Session Write Test ──
        $this->sectionHeader('Session File Test');

        $sessionPath = storage_path('framework/sessions');
        $testFile = $sessionPath . '/_diag_test_' . bin2hex(random_bytes(8));
        $testData = 'csrf_diag_' . time();

        // Write
        $written = @file_put_contents($testFile, $testData);
        $fails += $this->check('Write session file', $written !== false, $written !== false ? "{$written} bytes" : 'FAILED');

        // Read back
        if ($written !== false) {
            $readBack = @file_get_contents($testFile);
            $fails += $this->check('Read session file', $readBack === $testData, $readBack === $testData ? 'Data matches' : 'DATA MISMATCH');
            @unlink($testFile);
        }

        // ── Session Roundtrip via Laravel SessionManager ──
        $this->sectionHeader('Session Roundtrip');

        try {
            // Simulate what ForceFileSession does
            $originalDriver = config('session.driver');
            $originalCookie = config('session.cookie');

            config([
                'session.driver' => 'file',
                'session.files' => $sessionPath,
                'session.cookie' => 'browser-console-session',
            ]);

            // Create a fresh session manager to avoid cached drivers
            $manager = new \Illuminate\Session\SessionManager(app());
            $session = $manager->driver('file');
            $session->start();

            $token = \Illuminate\Support\Str::random(40);
            $session->put('_token', $token);
            $session->save();
            $sessionId = $session->getId();
            $this->check('Create file session', true, "ID: {$sessionId}");

            // Reload the session from the file (simulates the Livewire POST)
            $session2 = $manager->driver('file');
            $session2->setId($sessionId);
            $session2->start();
            $token2 = $session2->get('_token');
            $match = $token === $token2;
            $fails += $this->check(
                'Reload session & verify CSRF token',
                $match,
                $match ? 'Token persisted' : "MISMATCH: wrote {$token}, got {$token2}"
            );

            // Clean up test session file
            @unlink($sessionPath . '/' . $sessionId);

            // Restore original config
            config([
                'session.driver' => $originalDriver,
                'session.cookie' => $originalCookie,
            ]);
        } catch (\Throwable $e) {
            $fails += $this->check('Session roundtrip', false, $e->getMessage());
        }

        // ── PHP Settings ──
        $this->sectionHeader('PHP Settings');

        $this->check('SAPI', true, php_sapi_name());
        $this->check('SERVER_SOFTWARE', true, $_SERVER['SERVER_SOFTWARE'] ?? 'N/A (CLI)');

        $processUser = function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
            : get_current_user();
        $this->check('Process user', true, $processUser);

        $postMax = ini_get('post_max_size') ?: 'unknown';
        $this->check('post_max_size', true, $postMax);

        $maxInputVars = ini_get('max_input_vars') ?: 'unknown';
        $this->check('max_input_vars', true, $maxInputVars);

        $memoryLimit = ini_get('memory_limit') ?: 'unknown';
        $this->check('memory_limit', true, $memoryLimit);

        $maxExecTime = ini_get('max_execution_time') ?: '0';
        $this->check('max_execution_time', true, $maxExecTime . 's');

        $gcMaxLifetime = (int) ini_get('session.gc_maxlifetime');
        $fails += $this->check(
            'session.gc_maxlifetime',
            $gcMaxLifetime >= 1440,
            $gcMaxLifetime . 's (' . round($gcMaxLifetime / 60) . ' min)'
        );

        // ── HTTPS & Proxy ──
        $this->sectionHeader('HTTPS & Proxy');

        // Detect if behind a reverse proxy
        $hasForwardedFor = ! empty($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null);
        $hasForwardedProto = ! empty($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null);
        $isBehindProxy = $hasForwardedFor || $hasForwardedProto;
        $this->check('Behind reverse proxy', true, $isBehindProxy ? 'Likely (X-Forwarded headers present)' : 'No proxy headers detected');

        if ($hasForwardedProto) {
            $this->check('X-Forwarded-Proto', true, $_SERVER['HTTP_X_FORWARDED_PROTO']);
        }

        // TrustProxies middleware (Laravel 11+: HandlePrecognitiveRequests may replace)
        $trustProxiesInWeb = false;
        foreach ($webGroup as $mw) {
            if (is_string($mw) && (
                str_contains($mw, 'TrustProxies')
                || str_contains($mw, 'TrustedProxies')
            )) {
                $trustProxiesInWeb = true;

                break;
            }
        }
        // Also check kernel-level middleware (Laravel 11+ uses bootstrap/app.php)
        if (! $trustProxiesInWeb) {
            try {
                $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
                if (method_exists($kernel, 'getGlobalMiddleware')) {
                    foreach ($kernel->getGlobalMiddleware() as $mw) {
                        if (is_string($mw) && (str_contains($mw, 'TrustProxies') || str_contains($mw, 'TrustedProxies'))) {
                            $trustProxiesInWeb = true;

                            break;
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore — not available in all Laravel versions
            }
        }
        if ($isBehindProxy) {
            $fails += $this->check('TrustProxies middleware', $trustProxiesInWeb, $trustProxiesInWeb ? 'Found' : 'MISSING — proxy headers will be ignored');
        } else {
            $this->check('TrustProxies middleware', true, $trustProxiesInWeb ? 'Found' : 'Not found (not needed without proxy)');
        }

        // APP_URL scheme vs session.secure mismatch
        $appUrl = config('app.url', '');
        $appScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'unknown';
        $sessionSecure = config('session.secure');
        $this->check('APP_URL scheme', true, $appScheme);

        if ($appScheme === 'https' && $sessionSecure === false) {
            $this->info('    <fg=yellow>&#9888; APP_URL is HTTPS but session.secure is false — cookies sent on HTTP too</>');
        } elseif ($appScheme === 'http' && $sessionSecure === true) {
            $fails += $this->check(
                'Secure cookie vs HTTP APP_URL',
                false,
                'session.secure=true but APP_URL is HTTP — cookies will NOT be sent'
            );
        }

        // ── Cookie Encryption ──
        $this->sectionHeader('Cookie Encryption');

        $encryptInWeb = false;
        $encryptPos = false;
        $ssWebPos = false;
        foreach ($webGroup as $i => $mw) {
            if (is_string($mw) && (
                $mw === EncryptCookies::class
                || is_subclass_of($mw, EncryptCookies::class)
                || str_contains($mw, 'EncryptCookies')
            )) {
                $encryptInWeb = true;
                $encryptPos = $i;
            }
            if (is_string($mw) && (
                $mw === StartSession::class
                || is_subclass_of($mw, StartSession::class)
                || str_contains($mw, 'StartSession')
            )) {
                $ssWebPos = $i;
            }
        }

        $this->check('EncryptCookies in web group', $encryptInWeb, $encryptInWeb ? 'Position ' . $encryptPos : 'Not found');

        if ($encryptInWeb && $ssWebPos !== false) {
            $orderOk = $encryptPos < $ssWebPos;
            $this->check(
                'EncryptCookies before StartSession',
                $orderOk,
                $orderOk ? "Position {$encryptPos} < {$ssWebPos}" : "WRONG ORDER — session cookie decryption may fail"
            );
        }

        // ── OPcache ──
        $this->sectionHeader('OPcache');

        $opcacheLoaded = extension_loaded('Zend OPcache');
        if ($opcacheLoaded && ini_get('opcache.enable')) {
            $validate = ini_get('opcache.validate_timestamps');
            $fails += $this->check(
                'opcache.validate_timestamps',
                (bool) $validate,
                $validate ? 'On' : 'OFF — code changes need FPM restart'
            );
        } else {
            $this->check('OPcache', true, 'Disabled or not loaded');
        }

        return $fails;
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

    /**
     * Print an auto-fix notification.
     */
    private function fixed(string $label): void
    {
        $this->line("    <fg=yellow>&#8627; Fixed:</> {$label}");
    }
}
