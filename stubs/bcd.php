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
| Access is protected by the BROWSER_CONSOLE_PASSWORD from .env.
| If no password is configured, the page is locked entirely.
|
| Part of: codenzia/browser-console
|
*/

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
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

// --- Parse .env for authentication ---
$envPasswordHash = null;
$envContent = '';

if ($basePath && file_exists($basePath . '/.env')) {
    $envContent = file_get_contents($basePath . '/.env');

    if (preg_match('/^BROWSER_CONSOLE_PASSWORD=(.+)$/m', $envContent, $m)) {
        $envPasswordHash = trim($m[1]);
    }
}

// --- Authentication gate (PHP native sessions, no Laravel) ---
session_name('bcd_diagnostics');
session_start();

$authenticated = false;
$authError = '';

if ($envPasswordHash) {
    // Handle logout
    if (isset($_GET['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));

        exit;
    }

    // Handle login attempt
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && ! isset($_POST['fix'])) {
        if (password_verify($_POST['password'], $envPasswordHash)) {
            $_SESSION['bcd_authenticated'] = true;
            $_SESSION['bcd_auth_time'] = time();
            $authenticated = true;
        } else {
            $authError = 'Invalid password.';
        }
    }

    // Check existing session (30-minute timeout)
    if (! $authenticated && isset($_SESSION['bcd_authenticated'])) {
        $elapsed = time() - ($_SESSION['bcd_auth_time'] ?? 0);
        if ($elapsed < 1800) {
            $authenticated = true;
        } else {
            unset($_SESSION['bcd_authenticated'], $_SESSION['bcd_auth_time']);
        }
    }
} else {
    // No password configured — page is locked
    $authenticated = false;
}

// --- If not authenticated, show login or locked page and exit ---
if (! $authenticated) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>BCD - Locked</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0f172a; color: #e2e8f0; font-family: 'Courier New', monospace; font-size: 14px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 32px; max-width: 400px; width: 100%; }
        .card h1 { font-size: 16px; color: #38bdf8; margin-bottom: 4px; }
        .card .sub { color: #64748b; font-size: 12px; margin-bottom: 20px; }
        .card input { width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 10px 14px; color: #e2e8f0; font-family: inherit; font-size: 14px; margin-bottom: 12px; outline: none; }
        .card input:focus { border-color: #38bdf8; }
        .card button { width: 100%; background: #1d4ed8; color: #fff; border: none; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px; cursor: pointer; }
        .card button:hover { background: #2563eb; }
        .error { color: #f87171; font-size: 12px; margin-bottom: 12px; }
        .locked { color: #94a3b8; font-size: 13px; text-align: center; }
    </style>
</head>
<body>
<div class="card">
    <h1>Browser Console Diagnostics</h1>
    <div class="sub">codenzia/browser-console</div>
    <?php if ($envPasswordHash): ?>
        <?php if ($authError): ?>
            <div class="error"><?= htmlspecialchars($authError) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Console password" required autofocus>
            <button type="submit">Authenticate</button>
        </form>
    <?php else: ?>
        <p class="locked">
            Diagnostics locked.<br><br>
            Set BROWSER_CONSOLE_PASSWORD in .env<br>
            to enable access.
        </p>
    <?php endif; ?>
</div>
</body>
</html><?php
    exit;
}

// =====================================================================
//  AUTHENTICATED — Handle fix actions, then run diagnostics
// =====================================================================

$fixMessage = '';
$fixSuccess = false;

// --- Available fix actions ---
$fixActions = [];

if ($basePath) {
    // Directory creation fixes
    $dirs = [
        'storage', 'storage/app', 'storage/app/public',
        'storage/framework', 'storage/framework/sessions',
        'storage/framework/views', 'storage/framework/cache',
        'storage/logs', 'bootstrap/cache',
    ];
    foreach ($dirs as $dir) {
        $key = 'mkdir_' . str_replace('/', '_', $dir);
        $fixActions[$key] = function () use ($basePath, $dir) {
            $path = $basePath . '/' . $dir;
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            chmod($path, 0775);

            return "Created and set permissions: {$dir}";
        };

        $chmodKey = 'chmod_' . str_replace('/', '_', $dir);
        $fixActions[$chmodKey] = function () use ($basePath, $dir) {
            chmod($basePath . '/' . $dir, 0775);

            return "Fixed permissions: {$dir}";
        };
    }

    // .env from .env.example
    $fixActions['create_env'] = function () use ($basePath) {
        if (file_exists($basePath . '/.env.example')) {
            copy($basePath . '/.env.example', $basePath . '/.env');

            return 'Created .env from .env.example — run php artisan key:generate next';
        }

        return '.env.example not found — create .env manually';
    };

    // Remove public/hot (Vite dev leftover)
    $fixActions['remove_hot'] = function () use ($basePath) {
        unlink($basePath . '/public/hot');

        return 'Removed public/hot';
    };

    // Clear config cache
    $fixActions['clear_config_cache'] = function () use ($basePath) {
        $file = $basePath . '/bootstrap/cache/config.php';
        if (file_exists($file)) {
            unlink($file);
        }

        return 'Config cache cleared';
    };

    // Clear routes cache
    $fixActions['clear_routes_cache'] = function () use ($basePath) {
        $file = $basePath . '/bootstrap/cache/routes-v7.php';
        if (file_exists($file)) {
            unlink($file);
        }

        return 'Routes cache cleared';
    };

    // Clear compiled views
    $fixActions['clear_views'] = function () use ($basePath) {
        $dir = $basePath . '/storage/framework/views';
        $count = 0;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') as $file) {
                unlink($file);
                $count++;
            }
        }

        return "Cleared {$count} compiled view(s)";
    };

    // Clear package/service discovery cache
    $fixActions['clear_discovery_cache'] = function () use ($basePath) {
        $files = ['bootstrap/cache/packages.php', 'bootstrap/cache/services.php'];
        $cleared = 0;
        foreach ($files as $f) {
            $path = $basePath . '/' . $f;
            if (file_exists($path)) {
                unlink($path);
                $cleared++;
            }
        }

        return "Cleared {$cleared} discovery cache file(s)";
    };

    // Create storage symlink
    $fixActions['create_storage_link'] = function () use ($basePath) {
        $link = $basePath . '/public/storage';
        $target = $basePath . '/storage/app/public';
        if (! is_link($link) && is_dir($target)) {
            symlink($target, $link);

            return 'Created public/storage symlink';
        }

        return 'Could not create symlink — target missing or link exists';
    };

    // Restore .htaccess
    $fixActions['restore_htaccess'] = function () use ($basePath) {
        $htaccess = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS;
        file_put_contents($basePath . '/public/.htaccess', $htaccess);

        return 'Restored public/.htaccess with default Laravel rules';
    };

    // Fix .htaccess (missing RewriteEngine)
    $fixActions['fix_htaccess_rewrite'] = function () use ($basePath) {
        $path = $basePath . '/public/.htaccess';
        $content = file_get_contents($path);
        if (! preg_match('/RewriteEngine\s+On/i', $content)) {
            $content = "RewriteEngine On\n" . $content;
            file_put_contents($path, $content);

            return 'Added RewriteEngine On to .htaccess';
        }

        return 'RewriteEngine On already present';
    };

    // Reset OPcache
    $fixActions['reset_opcache'] = function () {
        if (function_exists('opcache_reset')) {
            opcache_reset();

            return 'OPcache cleared — cached bytecode has been invalidated';
        }

        return 'opcache_reset() not available';
    };
}

// --- Process fix request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    $fixId = $_POST['fix'];
    if (isset($fixActions[$fixId])) {
        try {
            $fixMessage = $fixActions[$fixId]();
            $fixSuccess = true;
        } catch (\Throwable $e) {
            $fixMessage = 'Fix failed: ' . $e->getMessage();
            $fixSuccess = false;
        }
    }
    // Re-read .env after fix (it may have been created)
    if ($basePath && file_exists($basePath . '/.env')) {
        $envContent = file_get_contents($basePath . '/.env');
    }
}

// --- Helper functions ---
function bc_check(string $label, bool $pass, string $detail = '', string $fix = '', string $fixId = ''): array
{
    return compact('label', 'pass', 'detail', 'fix', 'fixId');
}

function bc_check_writable(string $path, string $label): array
{
    $key = str_replace('/', '_', $label);
    if (! file_exists($path)) {
        return bc_check($label, false, 'Missing', "mkdir -p {$label} && chmod 775 {$label}", 'mkdir_' . $key);
    }
    if (! is_writable($path)) {
        return bc_check($label, false, 'Not writable', "chmod -R 775 {$label}", 'chmod_' . $key);
    }

    return bc_check($label, true, 'Writable');
}

// ================================================================
//  Section 1: PHP Environment
// ================================================================
$php = [];
$phpVersionOk = version_compare(PHP_VERSION, '8.2.0', '>=');
$php[] = bc_check('PHP Version >= 8.2', $phpVersionOk, PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.x', 'Upgrade PHP to 8.2+');
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

// OPcache
$opcacheLoaded = extension_loaded('Zend OPcache');
if ($opcacheLoaded) {
    $opcacheEnabled = (bool) ini_get('opcache.enable');
    $php[] = bc_check('OPcache', true, $opcacheEnabled ? 'Enabled (reset if stale code)' : 'Loaded but disabled', '', $opcacheEnabled ? 'reset_opcache' : '');
} else {
    $php[] = bc_check('OPcache', true, 'Not loaded (optional)');
}

// Disabled functions summary
$disabledFunctions = ini_get('disable_functions');
if ($disabledFunctions) {
    $disabled = array_map('trim', explode(',', $disabledFunctions));
    $dangerous = array_intersect($disabled, ['proc_open', 'exec', 'shell_exec', 'passthru', 'symlink']);
    if ($dangerous) {
        $php[] = bc_check('Disabled Functions', false, implode(', ', $dangerous), 'Remove from disable_functions in php.ini');
    }
}

// ================================================================
//  Section 2: Laravel Structure & Environment
// ================================================================
$structure = [];

if (! $basePath) {
    $structure[] = bc_check('Laravel Root', false, 'Could not find artisan + bootstrap/app.php', 'Ensure this file is inside Laravel\'s public/ directory');
} else {
    $structure[] = bc_check('Laravel Root', true, 'Detected');

    // .env
    $envExists = file_exists($basePath . '/.env');
    $envExampleExists = file_exists($basePath . '/.env.example');
    if ($envExists) {
        $structure[] = bc_check('.env', true, 'Found');
    } else {
        $fixHint = $envExampleExists ? 'cp .env.example .env && php artisan key:generate' : 'Create .env manually';
        $fixId = $envExampleExists ? 'create_env' : '';
        $structure[] = bc_check('.env', false, 'MISSING', $fixHint, $fixId);
    }

    if (! $envExampleExists) {
        $structure[] = bc_check('.env.example', false, 'MISSING (no reference for env vars)', 'Restore from your repository');
    }

    if ($envExists) {
        // APP_KEY
        $hasAppKey = (bool) preg_match('/^APP_KEY=base64:.+$/m', $envContent);
        $structure[] = bc_check('APP_KEY', $hasAppKey, $hasAppKey ? 'Set' : 'Empty or missing', 'php artisan key:generate');

        // APP_ENV
        $appEnv = 'unknown';
        if (preg_match('/^APP_ENV=(.+)$/m', $envContent, $m)) {
            $appEnv = trim($m[1]);
        }
        $structure[] = bc_check('APP_ENV', true, $appEnv);

        // APP_DEBUG
        $appDebug = (bool) preg_match('/^APP_DEBUG=true$/mi', $envContent);
        $isProduction = $appEnv === 'production';
        if ($isProduction && $appDebug) {
            $structure[] = bc_check('APP_DEBUG', false, 'true in production (security risk)', 'Set APP_DEBUG=false in .env');
        } else {
            $structure[] = bc_check('APP_DEBUG', true, $appDebug ? 'true (errors visible)' : 'false (errors hidden)');
        }

        // APP_URL
        if (preg_match('/^APP_URL=(.+)$/m', $envContent, $m)) {
            $appUrl = trim($m[1]);
            $isLocalhost = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');
            if ($isProduction && $isLocalhost) {
                $structure[] = bc_check('APP_URL', false, $appUrl . ' (still localhost in production)', 'Update APP_URL to your domain');
            } else {
                $structure[] = bc_check('APP_URL', true, $appUrl);
            }
        } else {
            $structure[] = bc_check('APP_URL', false, 'NOT SET', 'Add APP_URL=https://your-domain.com to .env');
        }
    }

    // Vendor
    $vendorExists = is_dir($basePath . '/vendor');
    $structure[] = bc_check('vendor/', $vendorExists, $vendorExists ? 'Found' : 'MISSING', 'composer install --no-dev --optimize-autoloader');

    $autoloadExists = file_exists($basePath . '/vendor/autoload.php');
    $structure[] = bc_check('vendor/autoload.php', $autoloadExists, $autoloadExists ? 'Found' : 'MISSING', 'composer dump-autoload');

    // composer.lock
    $composerLock = file_exists($basePath . '/composer.lock');
    $structure[] = bc_check('composer.lock', $composerLock, $composerLock ? 'Found' : 'MISSING (installs may not be reproducible)', 'Run composer install locally and commit composer.lock');

    // Caches
    $configCached = file_exists($basePath . '/bootstrap/cache/config.php');
    $structure[] = bc_check('Config Cache', true, $configCached ? 'Cached (clear if stale)' : 'Not cached', '', $configCached ? 'clear_config_cache' : '');

    $routesCached = file_exists($basePath . '/bootstrap/cache/routes-v7.php');
    $structure[] = bc_check('Routes Cache', true, $routesCached ? 'Cached (clear if stale)' : 'Not cached', '', $routesCached ? 'clear_routes_cache' : '');

    // Package/service discovery cache
    $pkgCache = file_exists($basePath . '/bootstrap/cache/packages.php');
    $svcCache = file_exists($basePath . '/bootstrap/cache/services.php');
    if ($pkgCache || $svcCache) {
        $parts = [];
        if ($pkgCache) {
            $parts[] = 'packages.php';
        }
        if ($svcCache) {
            $parts[] = 'services.php';
        }
        $structure[] = bc_check('Discovery Cache', true, implode(', ', $parts) . ' (clear if stale)', '', 'clear_discovery_cache');
    }

    // Compiled views
    $viewsDir = $basePath . '/storage/framework/views';
    if (is_dir($viewsDir)) {
        $compiledViews = count(glob($viewsDir . '/*.php'));
        if ($compiledViews > 0) {
            $structure[] = bc_check('Compiled Views', true, "{$compiledViews} file(s) (clear if stale)", '', 'clear_views');
        }
    }
}

// ================================================================
//  Section 3: Web Server & Public Directory
// ================================================================
$webserver = [];

if ($basePath) {
    // public/index.php
    $indexExists = file_exists($basePath . '/public/index.php');
    $webserver[] = bc_check('public/index.php', $indexExists, $indexExists ? 'Found' : 'MISSING (all routes broken)', 'Restore from laravel/laravel repository');

    // .htaccess
    $htaccessPath = $basePath . '/public/.htaccess';
    if (file_exists($htaccessPath)) {
        $htaccessContent = file_get_contents($htaccessPath);
        $hasRewriteEngine = (bool) preg_match('/RewriteEngine\s+On/i', $htaccessContent);
        $hasRewriteRule = str_contains($htaccessContent, 'index.php');
        $hasRewriteCond = str_contains($htaccessContent, 'REQUEST_FILENAME');

        if ($hasRewriteEngine && $hasRewriteRule && $hasRewriteCond) {
            $webserver[] = bc_check('.htaccess', true, 'Valid (RewriteEngine + rewrite rules)');
        } elseif (! $hasRewriteEngine) {
            $webserver[] = bc_check('.htaccess', false, 'Missing RewriteEngine On', 'Add RewriteEngine On directive', 'fix_htaccess_rewrite');
        } else {
            $webserver[] = bc_check('.htaccess', false, 'Missing rewrite rules', 'Restore default Laravel .htaccess', 'restore_htaccess');
        }
    } else {
        $webserver[] = bc_check('.htaccess', false, 'MISSING (routes will 404 on Apache)', 'Restore default Laravel .htaccess', 'restore_htaccess');
    }

    // public/hot (Vite dev leftover)
    $hotExists = file_exists($basePath . '/public/hot');
    if ($hotExists) {
        $webserver[] = bc_check('public/hot', false, 'EXISTS (Vite dev leftover — breaks asset URLs in production)', 'Delete public/hot', 'remove_hot');
    } else {
        $webserver[] = bc_check('public/hot', true, 'Not present');
    }

    // public/build/ (Vite built assets)
    $buildDir = $basePath . '/public/build';
    if (is_dir($buildDir)) {
        $manifestExists = file_exists($buildDir . '/manifest.json');
        if ($manifestExists) {
            $webserver[] = bc_check('public/build/', true, 'Found with manifest.json');
        } else {
            $webserver[] = bc_check('public/build/manifest.json', false, 'MISSING (Vite assets cannot resolve)', 'Run npm run build');
        }
    } else {
        // Only flag if package.json exists (app uses frontend build)
        if (file_exists($basePath . '/package.json')) {
            $webserver[] = bc_check('public/build/', false, 'MISSING (no built frontend assets)', 'Run npm install && npm run build');
        }
    }

    // public/storage symlink
    $publicStorage = $basePath . '/public/storage';
    if (is_link($publicStorage)) {
        $webserver[] = bc_check('public/storage symlink', true, 'Linked');
    } else {
        $webserver[] = bc_check('public/storage symlink', true, 'Not created (optional)', 'php artisan storage:link', 'create_storage_link');
    }
}

// ================================================================
//  Section 4: File & Directory Permissions
// ================================================================
$permissions = [];

if ($basePath) {
    $writables = [
        'storage'                    => '/storage',
        'storage/app'                => '/storage/app',
        'storage/app/public'         => '/storage/app/public',
        'storage/framework'          => '/storage/framework',
        'storage/framework/sessions' => '/storage/framework/sessions',
        'storage/framework/views'    => '/storage/framework/views',
        'storage/framework/cache'    => '/storage/framework/cache',
        'storage/logs'               => '/storage/logs',
        'bootstrap/cache'            => '/bootstrap/cache',
    ];
    foreach ($writables as $label => $dir) {
        $permissions[] = bc_check_writable($basePath . $dir, $label);
    }

    // Check public/ is readable (not writable check — just accessible)
    $publicReadable = is_readable($basePath . '/public');
    $permissions[] = bc_check('public/ (readable)', $publicReadable, $publicReadable ? 'Readable' : 'NOT READABLE', 'chmod 755 public');
}

// ================================================================
//  Section 5: Browser Console Package
// ================================================================
$package = [];

if ($basePath) {
    $pkgInstalled = is_dir($basePath . '/vendor/codenzia/browser-console');
    $package[] = bc_check('Package installed', $pkgInstalled, $pkgInstalled ? 'Found' : 'NOT FOUND', 'composer require codenzia/browser-console');

    $configPublished = file_exists($basePath . '/config/browser-console.php');
    $package[] = bc_check('Config published', $configPublished, $configPublished ? 'Found' : 'NOT PUBLISHED', 'php artisan vendor:publish --tag=browser-console-config');

    if ($envContent) {
        $hasUser = (bool) preg_match('/^BROWSER_CONSOLE_USER=.+$/m', $envContent);
        $package[] = bc_check('BROWSER_CONSOLE_USER', $hasUser, $hasUser ? 'Set' : 'NOT SET', 'php artisan browser-console:create');

        $hasPass = (bool) preg_match('/^BROWSER_CONSOLE_PASSWORD=\$.+$/m', $envContent);
        $package[] = bc_check('BROWSER_CONSOLE_PASSWORD', $hasPass, $hasPass ? 'Set' : 'NOT SET', 'php artisan browser-console:create');
    }

    $lwInstalled = is_dir($basePath . '/vendor/livewire/livewire');
    $package[] = bc_check('Livewire installed', $lwInstalled, $lwInstalled ? 'Found' : 'NOT FOUND', 'composer require livewire/livewire');
}

// ================================================================
//  Section 6: Laravel Boot Test (opt-in via ?boot=1)
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

        // Show relative paths only — never expose absolute server paths
        $bootErrorFile = str_replace($basePath . '/', '', $e->getFile()) . ':' . $e->getLine();

        // Collect the trace (first 5 frames, relative paths only)
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
$allSections = [$php, $structure, $webserver, $permissions, $package];
foreach ($allSections as $group) {
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
        .header { border: 1px solid #334155; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #1e293b; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left h1 { font-size: 18px; color: #38bdf8; margin-bottom: 4px; }
        .header-left .sub { color: #94a3b8; font-size: 12px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 13px; margin-top: 8px; }
        .logout { color: #64748b; font-size: 12px; text-decoration: none; border: 1px solid #334155; padding: 6px 12px; border-radius: 4px; }
        .logout:hover { color: #e2e8f0; border-color: #475569; }
        .flash { border-radius: 6px; padding: 10px 16px; margin-bottom: 16px; font-size: 13px; }
        .flash-ok { background: #052e16; border: 1px solid #166534; color: #4ade80; }
        .flash-err { background: #350808; border: 1px solid #7f1d1d; color: #fca5a5; }
        .section { border: 1px solid #334155; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
        .section-title { background: #1e293b; padding: 10px 16px; font-size: 13px; color: #38bdf8; border-bottom: 1px solid #334155; font-weight: bold; }
        .row { display: flex; padding: 6px 16px; border-bottom: 1px solid #1e293b; align-items: center; }
        .row:last-child { border-bottom: none; }
        .row:hover { background: #1e293b; }
        .icon { width: 20px; flex-shrink: 0; }
        .pass { color: #22c55e; }
        .fail { color: #ef4444; }
        .label { width: 260px; flex-shrink: 0; color: #cbd5e1; }
        .detail { flex: 1; color: #94a3b8; font-size: 13px; }
        .fix { color: #f59e0b; font-size: 12px; margin-left: 8px; }
        .fix-btn { background: #1d4ed8; color: #fff; border: none; border-radius: 4px; padding: 3px 10px; font-family: inherit; font-size: 11px; cursor: pointer; margin-left: 8px; flex-shrink: 0; }
        .fix-btn:hover { background: #2563eb; }
        .fix-btn.warn { background: #92400e; }
        .fix-btn.warn:hover { background: #b45309; }
        .boot-btn { display: inline-block; background: #1d4ed8; color: #fff; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-family: inherit; font-size: 13px; border: none; cursor: pointer; }
        .boot-btn:hover { background: #2563eb; }
        .boot-ok { background: #052e16; border: 1px solid #166534; border-radius: 6px; padding: 12px 16px; color: #4ade80; margin-top: 10px; }
        .boot-err { background: #350808; border: 1px solid #7f1d1d; border-radius: 6px; padding: 12px 16px; color: #fca5a5; margin-top: 10px; word-break: break-word; }
        .boot-err .error-msg { color: #fca5a5; font-weight: bold; }
        .boot-err .error-file { color: #94a3b8; font-size: 12px; margin-top: 4px; }
        .boot-err .trace { color: #6b7280; font-size: 11px; margin-top: 8px; }
        .boot-err .trace div { padding: 1px 0; }
        .footer { text-align: center; color: #475569; font-size: 11px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #1e293b; }
        @media (max-width: 640px) { .label { width: 140px; } .fix { display: block; margin-left: 0; margin-top: 2px; } }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <div class="header-left">
            <h1>Browser Console - Deployment Diagnostics</h1>
            <div class="sub">codenzia/browser-console &middot; <?= date('Y-m-d H:i:s T') ?></div>
            <div class="status" style="background: <?= $statusColor ?>20; color: <?= $statusColor ?>; border: 1px solid <?= $statusColor ?>;">
                <?= $statusText ?>
            </div>
        </div>
        <a href="?logout" class="logout">Logout</a>
    </div>

    <?php if ($fixMessage): ?>
        <div class="flash <?= $fixSuccess ? 'flash-ok' : 'flash-err' ?>">
            <?= htmlspecialchars($fixMessage) ?>
        </div>
    <?php endif; ?>

    <?php
    $sections = [
        'PHP Environment'            => $php,
        'Laravel Structure'          => $structure,
        'Web Server & Public'        => $webserver,
        'File & Directory Permissions' => $permissions,
        'Browser Console Package'    => $package,
    ];

    foreach ($sections as $title => $checks):
        if (empty($checks)) {
            continue;
        }
    ?>
        <div class="section">
            <div class="section-title"><?= $title ?></div>
            <?php foreach ($checks as $c): ?>
                <div class="row">
                    <span class="icon <?= $c['pass'] ? 'pass' : 'fail' ?>"><?= $c['pass'] ? '&#10004;' : '&#10008;' ?></span>
                    <span class="label"><?= htmlspecialchars($c['label']) ?></span>
                    <span class="detail">
                        <?= htmlspecialchars($c['detail']) ?>
                        <?php if (! $c['pass'] && $c['fix'] && ! $c['fixId']): ?>
                            <span class="fix">&rarr; <?= htmlspecialchars($c['fix']) ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($c['fixId'] && isset($fixActions[$c['fixId']])): ?>
                        <form method="POST" style="display:inline; margin:0;">
                            <input type="hidden" name="fix" value="<?= htmlspecialchars($c['fixId']) ?>">
                            <button type="submit" class="fix-btn <?= $c['pass'] ? 'warn' : '' ?>"><?= $c['pass'] ? 'Clear' : 'Fix' ?></button>
                        </form>
                    <?php endif; ?>
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
