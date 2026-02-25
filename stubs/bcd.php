<?php

/*
|--------------------------------------------------------------------------
| Browser Console - Deployment Diagnostics
|--------------------------------------------------------------------------
|
| Standalone diagnostics page for troubleshooting Browser Console
| deployment issues. This file has ZERO Laravel dependencies and works
| even when the framework fails to boot.
|
| SECURITY: Delete this file after diagnosing your issue.
|           Run: php artisan browser-console:diagnose --remove
|
| Part of: codenzia/browser-console
|
*/

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

// --- Detect Laravel root ---
$basePath = null;
$candidates = [
    __DIR__ . '/..',     // Standard: we're in public/
    __DIR__,             // We're placed in Laravel root directly
    __DIR__ . '/../..',  // Nested: public_html/public/
];

foreach ($candidates as $candidate) {
    if (file_exists($candidate . '/artisan') && file_exists($candidate . '/bootstrap/app.php')) {
        $basePath = realpath($candidate);

        break;
    }
}

// --- Helper functions ---
function bc_check(string $label, bool $pass, string $detail = '', string $fix = ''): array
{
    return compact('label', 'pass', 'detail', 'fix');
}

function bc_check_writable(string $path, string $label): array
{
    if (! file_exists($path)) {
        return bc_check($label, false, 'Missing', "mkdir -p {$label} && chmod 775 {$label}");
    }
    if (! is_writable($path)) {
        return bc_check($label, false, 'Not writable (perms: ' . substr(sprintf('%o', fileperms($path)), -4) . ')', "chmod -R 775 {$label}");
    }

    return bc_check($label, true, 'Writable (' . substr(sprintf('%o', fileperms($path)), -4) . ')');
}

// ================================================================
//  Section 1: PHP Environment
// ================================================================
$php = [];
$php[] = bc_check('PHP Version >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION, 'Upgrade PHP to 8.2+');
$php[] = bc_check('SAPI', true, php_sapi_name());
$php[] = bc_check('Memory Limit', true, ini_get('memory_limit'));
$php[] = bc_check('Max Execution Time', true, ini_get('max_execution_time') . 's');

$requiredExts = ['json', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'dom', 'curl', 'pdo'];
foreach ($requiredExts as $ext) {
    $loaded = extension_loaded($ext);
    $php[] = bc_check("ext-{$ext}", $loaded, $loaded ? 'Loaded' : 'MISSING', "Enable the {$ext} extension in php.ini");
}

$procOpen = function_exists('proc_open');
$php[] = bc_check('proc_open()', $procOpen, $procOpen ? 'Available' : 'DISABLED', 'Remove proc_open from disable_functions in php.ini');

$symlink = function_exists('symlink');
$php[] = bc_check('symlink()', $symlink, $symlink ? 'Available' : 'DISABLED', 'Remove symlink from disable_functions in php.ini');

// ================================================================
//  Section 2: Laravel Structure
// ================================================================
$structure = [];

if (! $basePath) {
    $structure[] = bc_check('Laravel Root', false, 'Could not find artisan + bootstrap/app.php', 'Ensure this file is inside Laravel\'s public/ directory');
} else {
    $structure[] = bc_check('Laravel Root', true, $basePath);

    $envExists = file_exists($basePath . '/.env');
    $structure[] = bc_check('.env', $envExists, $envExists ? 'Found' : 'MISSING', 'cp .env.example .env && php artisan key:generate');

    if ($envExists) {
        $envContent = file_get_contents($basePath . '/.env');

        $hasAppKey = (bool) preg_match('/^APP_KEY=base64:.+$/m', $envContent);
        $structure[] = bc_check('APP_KEY', $hasAppKey, $hasAppKey ? 'Set' : 'Empty or missing', 'php artisan key:generate');

        $appDebug = (bool) preg_match('/^APP_DEBUG=true$/mi', $envContent);
        $structure[] = bc_check('APP_DEBUG', true, $appDebug ? 'true (errors visible)' : 'false (errors hidden)');
    }

    $vendorExists = is_dir($basePath . '/vendor');
    $structure[] = bc_check('vendor/', $vendorExists, $vendorExists ? 'Found' : 'MISSING', 'composer install --no-dev --optimize-autoloader');

    $autoloadExists = file_exists($basePath . '/vendor/autoload.php');
    $structure[] = bc_check('vendor/autoload.php', $autoloadExists, $autoloadExists ? 'Found' : 'MISSING', 'composer dump-autoload');

    // Check for cached config (common source of 500 errors after deploy)
    $configCached = file_exists($basePath . '/bootstrap/cache/config.php');
    $structure[] = bc_check('Config Cache', true, $configCached ? 'Cached (clear if stale: php artisan config:clear)' : 'Not cached');

    $routesCached = file_exists($basePath . '/bootstrap/cache/routes-v7.php');
    $structure[] = bc_check('Routes Cache', true, $routesCached ? 'Cached (clear if stale: php artisan route:clear)' : 'Not cached');

    // Writable directories
    $writables = [
        'storage'                    => '/storage',
        'storage/framework'          => '/storage/framework',
        'storage/framework/sessions' => '/storage/framework/sessions',
        'storage/framework/views'    => '/storage/framework/views',
        'storage/framework/cache'    => '/storage/framework/cache',
        'storage/logs'               => '/storage/logs',
        'bootstrap/cache'            => '/bootstrap/cache',
    ];
    foreach ($writables as $label => $dir) {
        $structure[] = bc_check_writable($basePath . $dir, $label);
    }

    // storage/app symlink check
    $publicStorage = $basePath . '/public/storage';
    if (is_link($publicStorage)) {
        $target = readlink($publicStorage);
        $structure[] = bc_check('public/storage symlink', true, "-> {$target}");
    } else {
        $structure[] = bc_check('public/storage symlink', true, 'Not created (optional: php artisan storage:link)');
    }
}

// ================================================================
//  Section 3: Browser Console Package
// ================================================================
$package = [];

if ($basePath) {
    $pkgInstalled = is_dir($basePath . '/vendor/codenzia/browser-console');
    $package[] = bc_check('Package installed', $pkgInstalled, $pkgInstalled ? 'vendor/codenzia/browser-console' : 'NOT FOUND', 'composer require codenzia/browser-console');

    $configPublished = file_exists($basePath . '/config/browser-console.php');
    $package[] = bc_check('Config published', $configPublished, $configPublished ? 'config/browser-console.php' : 'NOT PUBLISHED', 'php artisan vendor:publish --tag=browser-console-config');

    if (file_exists($basePath . '/.env')) {
        $envContent = $envContent ?? file_get_contents($basePath . '/.env');

        $hasUser = (bool) preg_match('/^BROWSER_CONSOLE_USER=.+$/m', $envContent);
        $package[] = bc_check('BROWSER_CONSOLE_USER', $hasUser, $hasUser ? 'Set (value hidden)' : 'NOT SET', 'php artisan browser-console:create');

        $hasPass = (bool) preg_match('/^BROWSER_CONSOLE_PASSWORD=\$.+$/m', $envContent);
        $package[] = bc_check('BROWSER_CONSOLE_PASSWORD', $hasPass, $hasPass ? 'Set (value hidden)' : 'NOT SET', 'php artisan browser-console:create');
    }

    $lwInstalled = is_dir($basePath . '/vendor/livewire/livewire');
    $package[] = bc_check('Livewire installed', $lwInstalled, $lwInstalled ? 'Found' : 'NOT FOUND', 'composer require livewire/livewire');

    // Check public/.htaccess for Livewire/Laravel rewrite rules
    $htaccess = $basePath . '/public/.htaccess';
    if (file_exists($htaccess)) {
        $package[] = bc_check('public/.htaccess', true, 'Found');
    } else {
        $package[] = bc_check('public/.htaccess', false, 'MISSING', 'Restore from laravel/laravel repository');
    }
}

// ================================================================
//  Section 4: Laravel Boot Test (opt-in via ?boot=1)
// ================================================================
$bootRequested = isset($_GET['boot']);
$bootResult = null;
$bootError = null;
$bootErrorFile = null;

if ($bootRequested && $basePath && file_exists($basePath . '/vendor/autoload.php')) {
    ob_start();

    try {
        require $basePath . '/vendor/autoload.php';
        $app = require $basePath . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $app->boot();

        $bootResult = 'Laravel booted successfully (' . $app->version() . ')';

        // Check if console route is registered
        try {
            $consolePath = $app->make('config')->get('browser-console.path', 'console');
            $router = $app->make('router');
            $routes = $router->getRoutes();
            $testRequest = \Illuminate\Http\Request::create('/' . $consolePath, 'GET');
            $matched = $routes->match($testRequest);

            if ($matched) {
                $bootResult .= " | Route '/{$consolePath}' registered";
            }
        } catch (\Throwable $routeErr) {
            $bootResult .= ' | Route check failed: ' . $routeErr->getMessage();
        }
    } catch (\Throwable $e) {
        $bootError = get_class($e) . ': ' . $e->getMessage();
        $bootErrorFile = str_replace($basePath . '/', '', $e->getFile()) . ':' . $e->getLine();

        // Collect the trace (first 5 frames, relative paths)
        $trace = [];
        foreach (array_slice($e->getTrace(), 0, 5) as $frame) {
            $file = isset($frame['file']) ? str_replace($basePath . '/', '', $frame['file']) : '(internal)';
            $line = $frame['line'] ?? '?';
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '?');
            $trace[] = "{$file}:{$line} {$call}()";
        }
    }

    $bootOutput = ob_get_clean();
}

// ================================================================
//  Count results
// ================================================================
$totalFails = 0;
foreach ([$php, $structure, $package] as $group) {
    foreach ($group as $c) {
        if (! $c['pass']) {
            $totalFails++;
        }
    }
}
if ($bootError) {
    $totalFails++;
}

$statusColor = $totalFails === 0 ? '#22c55e' : ($totalFails <= 3 ? '#f59e0b' : '#ef4444');
$statusText = $totalFails === 0 ? 'ALL CHECKS PASSED' : "{$totalFails} ISSUE(S) FOUND";

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Browser Console - Diagnostics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0f172a; color: #e2e8f0; font-family: 'Courier New', monospace; font-size: 14px; padding: 20px; line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { border: 1px solid #334155; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #1e293b; }
        .header h1 { font-size: 18px; color: #38bdf8; margin-bottom: 4px; }
        .header .sub { color: #94a3b8; font-size: 12px; }
        .warning { background: #451a03; border: 1px solid #92400e; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; color: #fbbf24; font-size: 12px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 13px; margin-top: 8px; }
        .section { border: 1px solid #334155; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
        .section-title { background: #1e293b; padding: 10px 16px; font-size: 13px; color: #38bdf8; border-bottom: 1px solid #334155; font-weight: bold; }
        .row { display: flex; padding: 6px 16px; border-bottom: 1px solid #1e293b; align-items: baseline; }
        .row:last-child { border-bottom: none; }
        .row:hover { background: #1e293b; }
        .icon { width: 20px; flex-shrink: 0; }
        .pass { color: #22c55e; }
        .fail { color: #ef4444; }
        .label { width: 280px; flex-shrink: 0; color: #cbd5e1; }
        .detail { flex: 1; color: #94a3b8; font-size: 13px; }
        .fix { color: #f59e0b; font-size: 12px; margin-left: 8px; }
        .boot-section { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .boot-btn { display: inline-block; background: #1d4ed8; color: #fff; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-family: inherit; font-size: 13px; border: none; cursor: pointer; }
        .boot-btn:hover { background: #2563eb; }
        .boot-ok { background: #052e16; border: 1px solid #166534; border-radius: 6px; padding: 12px 16px; color: #4ade80; margin-top: 10px; }
        .boot-err { background: #350808; border: 1px solid #7f1d1d; border-radius: 6px; padding: 12px 16px; color: #fca5a5; margin-top: 10px; word-break: break-word; }
        .boot-err .error-msg { color: #fca5a5; font-weight: bold; }
        .boot-err .error-file { color: #94a3b8; font-size: 12px; margin-top: 4px; }
        .boot-err .trace { color: #6b7280; font-size: 11px; margin-top: 8px; }
        .boot-err .trace div { padding: 1px 0; }
        .footer { text-align: center; color: #475569; font-size: 11px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #1e293b; }
        @media (max-width: 640px) { .label { width: 160px; } .fix { display: block; margin-left: 0; margin-top: 2px; } }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>Browser Console - Deployment Diagnostics</h1>
        <div class="sub">codenzia/browser-console &middot; <?= date('Y-m-d H:i:s T') ?></div>
        <div class="status" style="background: <?= $statusColor ?>20; color: <?= $statusColor ?>; border: 1px solid <?= $statusColor ?>;">
            <?= $statusText ?>
        </div>
    </div>

    <div class="warning">
        &#9888; SECURITY: Delete this file after diagnosing. It exposes server information.<br>
        Run: <strong>php artisan browser-console:diagnose --remove</strong> &nbsp;or&nbsp; manually delete <strong>public/bcd.php</strong>
    </div>

    <?php
    $sections = [
        'PHP Environment'               => $php,
        'Laravel Structure'              => $structure,
        'Browser Console Package'        => $package,
    ];

    foreach ($sections as $title => $checks): ?>
        <div class="section">
            <div class="section-title"><?= $title ?></div>
            <?php foreach ($checks as $c): ?>
                <div class="row">
                    <span class="icon <?= $c['pass'] ? 'pass' : 'fail' ?>"><?= $c['pass'] ? '&#10004;' : '&#10008;' ?></span>
                    <span class="label"><?= htmlspecialchars($c['label']) ?></span>
                    <span class="detail">
                        <?= htmlspecialchars($c['detail']) ?>
                        <?php if (! $c['pass'] && $c['fix']): ?>
                            <span class="fix">&rarr; <?= htmlspecialchars($c['fix']) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Laravel Boot Test -->
    <div class="section">
        <div class="section-title">Laravel Boot Test</div>
        <div style="padding: 12px 16px;">
            <?php if (! $bootRequested): ?>
                <p style="color: #94a3b8; margin-bottom: 10px; font-size: 13px;">
                    Attempts to bootstrap Laravel and reports the exact error. This is the most useful check for diagnosing 500 errors.
                </p>
                <a href="?boot=1" class="boot-btn">Run Boot Test</a>
            <?php elseif ($bootResult): ?>
                <div class="boot-ok"><?= htmlspecialchars($bootResult) ?></div>
            <?php elseif ($bootError): ?>
                <div class="boot-err">
                    <div class="error-msg"><?= htmlspecialchars($bootError) ?></div>
                    <?php if ($bootErrorFile): ?>
                        <div class="error-file">at <?= htmlspecialchars($bootErrorFile) ?></div>
                    <?php endif; ?>
                    <?php if (! empty($trace)): ?>
                        <div class="trace">
                            <?php foreach ($trace as $frame): ?>
                                <div><?= htmlspecialchars($frame) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (! empty($bootOutput)): ?>
                    <pre style="margin-top: 10px; color: #6b7280; font-size: 11px; white-space: pre-wrap;"><?= htmlspecialchars(substr($bootOutput, 0, 2000)) ?></pre>
                <?php endif; ?>
            <?php else: ?>
                <div class="boot-err">
                    <div class="error-msg">Could not attempt boot: vendor/autoload.php not found.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        codenzia/browser-console &middot; diagnostics v1.0
    </div>

</div>
</body>
</html>
