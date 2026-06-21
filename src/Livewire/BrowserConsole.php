<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole\Livewire;

use Codenzia\BrowserConsole\Support\Audit;
use Codenzia\BrowserConsole\Support\ConsoleAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class BrowserConsole extends Component
{
    public string $username = '';

    public string $password = '';

    public string $command = '';

    public string $mode = 'artisan';

    public string $loginError = '';

    public bool $shellAvailable = true;

    public bool $isRunning = false;

    /** @var array<int, array{command: string, output: string, status: string, timestamp: string, mode: string}> */
    #[Locked]
    public array $history = [];

    // Log viewer
    public int $logLines = 100;

    public string $logLevel = 'all';

    /** @var array<int, array{timestamp: string, level: string, message: string}> */
    #[Locked]
    public array $logEntries = [];

    // Reference panel
    public string $refTab = 'commands';

    public string $commandSearch = '';

    // Debug viewer
    /** @var array<int, array{id: string, ts: string, type: string, values: array, label: ?string, color: string, caller: array}> */
    #[Locked]
    public array $debugEntries = [];

    /** Cached command reference — built once in mount(), avoids Artisan::all() on every XHR. */
    /** @var array<string, array<int, array{command: string, description: string}>> */
    public array $commandGroups = [];

    public function mount(): void
    {
        $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
        $this->shellAvailable = ! in_array('proc_open', $disabled, true);

        // Build command reference once on page load (matches working version's @js() approach).
        // This prevents Artisan::all() from being called during every Livewire XHR update.
        $this->commandGroups = $this->getCommandReference();
    }

    public function getIsAuthenticatedProperty(): bool
    {
        // Custom gate: if defined and passes, bypass username/password auth
        $gate = config('browser-console.gate');
        if ($gate && is_callable($gate)) {
            return (bool) call_user_func($gate, request());
        }

        return ConsoleAuth::check(request());
    }

    /**
     * Abort the Livewire request unless the caller is authenticated.
     * Use on every action that reads or mutates server-side data.
     */
    private function ensureAuthenticated(): void
    {
        if (! $this->isAuthenticated) {
            abort(Response::HTTP_FORBIDDEN, 'Console authentication required.');
        }
    }

    public function authenticate(): void
    {
        $configUser = config('browser-console.user');
        $configPassword = config('browser-console.password');

        if (! $configUser || ! $configPassword) {
            $this->loginError = 'Console access not configured. Run: php artisan browser-console:create';
            Audit::event('console.login.failed', $this->loginAuditContext('not_configured'));

            return;
        }

        // Dedicated per-IP brute-force limiter (independent of the route throttle
        // so legitimate command/poll traffic is never penalised). 5 attempts/min.
        $limiterKey = $this->loginRateLimiterKey();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            $this->loginError = "Too many login attempts. Try again in {$seconds} second(s).";
            $this->password = '';
            Audit::event('console.login.failed', $this->loginAuditContext('rate_limited'));

            return;
        }

        // Constant-time comparison of both username and password. Always run
        // Hash::check so the timing profile does not reveal whether the username
        // matched (mitigates username enumeration — see LOW-01).
        $userOk = hash_equals((string) $configUser, $this->username);
        $passOk = Hash::check($this->password, (string) $configPassword);

        if (! $userOk || ! $passOk) {
            RateLimiter::hit($limiterKey, 60);
            $this->loginError = 'Invalid credentials.';
            $this->password = '';
            Audit::event('console.login.failed', $this->loginAuditContext('bad_password'));

            return;
        }

        RateLimiter::clear($limiterKey);
        ConsoleAuth::login();
        $this->username = '';
        $this->password = '';
        Audit::event('console.login.success', $this->loginAuditContext());
    }

    /**
     * Rate-limiter key for login attempts, scoped to the requesting IP.
     */
    private function loginRateLimiterKey(): string
    {
        return 'browser-console:login:'.(request()?->ip() ?? 'unknown');
    }

    /**
     * @return array<string, mixed>
     */
    private function loginAuditContext(?string $reason = null): array
    {
        $request = request();

        $context = [
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'route' => $request?->path(),
        ];

        if ($reason !== null) {
            $context['reason'] = $reason;
        }

        return $context;
    }

    public function logout(): void
    {
        ConsoleAuth::logout();
        $this->history = [];
        $this->command = '';
    }

    public function switchMode(string $mode): void
    {
        $this->ensureAuthenticated();

        if (in_array($mode, ['artisan', 'shell', 'logs', 'debug'], true)) {
            $this->mode = $mode;
            $this->command = '';
            $this->commandSearch = '';

            if ($mode === 'logs') {
                $this->loadLogs();
            }

            if ($mode === 'debug') {
                $this->loadDebugEntries();
            }
        }
    }

    public function fillCommand(string $cmd, string $mode = ''): void
    {
        // Switch mode only when explicitly requested and different from current
        if ($mode && $mode !== $this->mode && in_array($mode, ['artisan', 'shell'], true)) {
            $this->mode = $mode;
        }

        $this->command = $cmd;
    }

    public function runCommand(): void
    {
        if (! $this->isAuthenticated || $this->isRunning) {
            return;
        }

        $input = trim($this->command);

        if ($input === '') {
            return;
        }

        $this->isRunning = true;
        $this->command = '';

        try {
            if ($this->mode === 'shell') {
                $this->runShellCommand($input);
            } else {
                $this->runArtisanCommand($input);
            }
        } finally {
            $this->isRunning = false;
            $this->dispatch('scroll-to-bottom');
        }
    }

    public function clearHistory(): void
    {
        $this->ensureAuthenticated();

        $this->history = [];
    }

    /** @return array<string, array<int, array{command: string, description: string}>> */
    public function getCommandReference(): array
    {
        // Buffer stray PHP output — Artisan::all() autoloads commands which
        // can trigger deprecation notices that corrupt Livewire's JSON response.
        ob_start();

        try {
            $groups = $this->discoverArtisanCommands();

            $seeders = $this->discoverSeeders();
            if ($seeders) {
                $groups['Seeders'] = $seeders;
            }

            return $groups;
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Discover all registered Artisan commands and group them by namespace prefix.
     *
     * @return array<string, array<int, array{command: string, description: string}>>
     */
    private function discoverArtisanCommands(): array
    {
        $allCommands = Artisan::all();

        // Define which command groups to show (prefix => display label)
        $groupMap = [
            'optimize' => 'Cache',
            'config' => 'Cache',
            'route' => 'Cache',
            'view' => 'Cache',
            'event' => 'Cache',
            'cache' => 'Cache',
            'icon' => 'Cache',
            'migrate' => 'Database',
            'db' => 'Database',
            'shield' => 'Permissions',
            'queue' => 'Queue',
            'storage' => 'System',
            'schedule' => 'System',
            'about' => 'System',
            'up' => 'System',
            'down' => 'System',
        ];

        // Commands with recommended flags for production use
        $flagOverrides = [
            'migrate' => '--force',
            'migrate:rollback' => '--force',
            'db:seed' => '--force',
            'shield:generate' => '--all --panel=admin',
            'down' => '--refresh=15',
        ];

        // Sort order for groups
        $groupOrder = [
            'Cache' => 1,
            'Database' => 2,
            'Permissions' => 3,
            'Queue' => 4,
            'System' => 5,
            'App' => 6,
        ];

        $groups = [];

        foreach ($allCommands as $name => $command) {
            $prefix = Str::before($name, ':') ?: $name;
            $className = $command::class;

            // App-specific commands (from App\ namespace)
            if (str_starts_with($className, 'App\\')) {
                $groupLabel = 'App';
            } elseif (isset($groupMap[$prefix])) {
                $groupLabel = $groupMap[$prefix];
            } elseif (isset($groupMap[$name])) {
                $groupLabel = $groupMap[$name];
            } else {
                continue; // Skip commands not in our groups
            }

            $displayCommand = $name;
            if (isset($flagOverrides[$name])) {
                $displayCommand .= ' '.$flagOverrides[$name];
            }

            $groups[$groupLabel][] = [
                'command' => $displayCommand,
                'description' => $command->getDescription() ?: $name,
            ];
        }

        // Sort commands within each group alphabetically
        foreach ($groups as &$commands) {
            usort($commands, fn (array $a, array $b): int => strcmp($a['command'], $b['command']));
        }

        // Sort groups by defined order
        uksort($groups, function (string $a, string $b) use ($groupOrder): int {
            return ($groupOrder[$a] ?? 99) <=> ($groupOrder[$b] ?? 99);
        });

        return $groups;
    }

    /**
     * Discover seeder classes from database/seeders directory.
     *
     * @return array<int, array{command: string, description: string}>
     */
    private function discoverSeeders(): array
    {
        $seeders = [];
        $seederPath = base_path('database/seeders');

        if (! is_dir($seederPath)) {
            return $seeders;
        }

        // Default seeder (runs DatabaseSeeder)
        $seeders[] = [
            'command' => 'db:seed --force',
            'description' => 'Run default DatabaseSeeder',
        ];

        $files = glob($seederPath.'/*Seeder.php') ?: [];

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);

            // Skip DatabaseSeeder (already covered above)
            if ($className === 'DatabaseSeeder') {
                continue;
            }

            // Human-readable name: DemoPropertySeeder → Demo Property
            $label = str_replace('Seeder', '', $className);
            $label = Str::headline($label);

            $seeders[] = [
                'command' => 'db:seed --force --class='.$className,
                'description' => $label,
            ];
        }

        return $seeders;
    }

    /** @return array<string, array<int, array{command: string, description: string}>> */
    public function getShellCommandReference(): array
    {
        return [
            'Composer' => [
                ['command' => 'composer install --no-dev', 'description' => 'Install production dependencies'],
                ['command' => 'composer update --no-dev', 'description' => 'Update production dependencies'],
                ['command' => 'composer require', 'description' => 'Require a new package (add name)'],
                ['command' => 'composer remove', 'description' => 'Remove a package (add name)'],
                ['command' => 'composer dump-autoload -o', 'description' => 'Optimize autoloader'],
                ['command' => 'composer show --installed', 'description' => 'List installed packages'],
                ['command' => 'composer diagnose', 'description' => 'Diagnose common issues'],
            ],
            'Git' => [
                ['command' => 'git status', 'description' => 'Show working tree status'],
                ['command' => 'git log --oneline -15', 'description' => 'Show recent commits'],
                ['command' => 'git pull', 'description' => 'Pull latest changes'],
                ['command' => 'git diff --stat', 'description' => 'Show changed files summary'],
                ['command' => 'git branch -a', 'description' => 'List all branches'],
                ['command' => 'git checkout', 'description' => 'Switch branch (add name)'],
                ['command' => 'git remote -v', 'description' => 'Show remote URLs'],
            ],
            'PHP Info' => [
                ['command' => 'php -v', 'description' => 'Show PHP version'],
                ['command' => 'php -m', 'description' => 'List loaded extensions'],
                ['command' => 'php -i', 'description' => 'Full PHP configuration info'],
                ['command' => 'which php', 'description' => 'Show PHP binary path'],
            ],
            'System' => [
                ['command' => 'ls -la', 'description' => 'List files in project root'],
                ['command' => 'pwd', 'description' => 'Show current directory'],
                ['command' => 'whoami', 'description' => 'Show current user'],
                ['command' => 'df -h', 'description' => 'Show disk usage'],
                ['command' => 'du -sh storage', 'description' => 'Show storage directory size'],
            ],
            'Storage & Symlinks' => [
                ['command' => 'ls -la public/storage', 'description' => 'Check public storage symlink'],
                ['command' => 'readlink -f public/storage', 'description' => 'Show symlink target path'],
                ['command' => 'ls -la storage/app/public', 'description' => 'List storage public files'],
                ['command' => 'ln -s storage/app/public public/storage', 'description' => 'Create storage symlink'],
            ],
        ];
    }

    /** @return array<string, array<int, array{step: int, command: string, title: string, description: string, mode: string}>> */
    public function getDeploymentGuide(): array
    {
        return [
            'Fresh Deployment' => [
                ['step' => 1, 'command' => 'optimize:clear', 'title' => 'Clear caches', 'description' => 'Remove all stale cached data from previous deployment', 'mode' => 'artisan'],
                ['step' => 2, 'command' => 'migrate --force', 'title' => 'Run migrations', 'description' => 'Create/update database tables', 'mode' => 'artisan'],
                ['step' => 3, 'command' => 'db:seed --force', 'title' => 'Seed database', 'description' => 'Populate initial data (countries, currencies, etc.)', 'mode' => 'artisan'],
                ['step' => 4, 'command' => 'shield:generate --all --panel=admin', 'title' => 'Generate permissions', 'description' => 'Create RBAC permissions for all resources', 'mode' => 'artisan'],
                ['step' => 5, 'command' => 'shield:super-admin', 'title' => 'Create super admin', 'description' => 'Assign super_admin role to a user', 'mode' => 'artisan'],
                ['step' => 6, 'command' => 'storage:link', 'title' => 'Storage symlink', 'description' => 'Link public/storage to storage/app/public', 'mode' => 'artisan'],
                ['step' => 7, 'command' => 'optimize', 'title' => 'Cache for production', 'description' => 'Cache config, routes, views & events', 'mode' => 'artisan'],
            ],
            'Re-deployment / Update' => [
                ['step' => 1, 'command' => 'optimize:clear', 'title' => 'Clear caches', 'description' => 'Must clear old caches before updating', 'mode' => 'artisan'],
                ['step' => 2, 'command' => 'migrate --force', 'title' => 'Run migrations', 'description' => 'Apply any new/pending migrations', 'mode' => 'artisan'],
                ['step' => 3, 'command' => 'shield:generate --all --panel=admin', 'title' => 'Sync permissions', 'description' => 'Regenerate permissions for new/changed resources', 'mode' => 'artisan'],
                ['step' => 4, 'command' => 'optimize', 'title' => 'Cache for production', 'description' => 'Re-cache everything with updated code', 'mode' => 'artisan'],
            ],
        ];
    }

    /** @return array<string, array<int, array{command: string, description: string}>> */
    public function getFilteredCommandReference(): array
    {
        $commands = $this->mode === 'shell'
            ? $this->getShellCommandReference()
            : $this->commandGroups;

        if ($this->commandSearch === '') {
            return $commands;
        }

        $search = strtolower($this->commandSearch);
        $filtered = [];

        foreach ($commands as $group => $cmds) {
            $matched = array_filter(
                $cmds,
                fn (array $cmd): bool => str_contains(strtolower($cmd['command']), $search)
                    || str_contains(strtolower($cmd['description']), $search),
            );

            if ($matched) {
                $filtered[$group] = array_values($matched);
            }
        }

        return $filtered;
    }

    public function loadLogs(): void
    {
        $this->ensureAuthenticated();

        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            $this->logEntries = [];

            return;
        }

        // Read only the tail of the file to avoid loading hundreds of MB into memory.
        // We estimate ~500 bytes per raw line and need logLines*5 raw lines to
        // produce enough parsed entries after multiline grouping and filtering.
        $lines = $this->readTailLines($logPath, $this->logLines * 5);

        // Parse multiline log entries — each starts with [YYYY-MM-DD HH:MM:SS]
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.*)$/', $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'message' => $matches[3],
                ];
            } elseif ($current !== null) {
                // Append stack trace / continuation lines
                $current['message'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        // Filter by level
        if ($this->logLevel !== 'all') {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry): bool => $entry['level'] === $this->logLevel,
            ));
        }

        // Take last N entries, reverse for newest-first
        $entries = array_slice($entries, -$this->logLines);
        $entries = array_reverse($entries);

        // Truncate long messages for display
        foreach ($entries as &$entry) {
            if (strlen($entry['message']) > 500) {
                $entry['message'] = substr($entry['message'], 0, 500).'…';
            }
        }

        $this->logEntries = $entries;
    }

    public function clearLog(): void
    {
        $this->ensureAuthenticated();

        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        $this->logEntries = [];
    }

    public function downloadLog(): Response
    {
        $this->ensureAuthenticated();

        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            return response()->noContent();
        }

        return response()->download($logPath, 'laravel-'.date('Y-m-d').'.log');
    }

    public function updatedLogLines(): void
    {
        $this->ensureAuthenticated();

        $this->loadLogs();
    }

    public function updatedLogLevel(): void
    {
        $this->ensureAuthenticated();

        $this->loadLogs();
    }

    public function loadDebugEntries(): void
    {
        $this->ensureAuthenticated();

        $logPath = storage_path('logs/console-debug.log');

        if (! File::exists($logPath)) {
            $this->debugEntries = [];

            return;
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        // Take last 200 entries, newest first
        $lines = array_slice($lines, -200);
        $lines = array_reverse($lines);

        $entries = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);

            if (is_array($entry) && isset($entry['id'], $entry['ts'])) {
                $entries[] = $entry;
            }
        }

        $this->debugEntries = $entries;
    }

    public function clearDebugEntries(): void
    {
        $this->ensureAuthenticated();

        $logPath = storage_path('logs/console-debug.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        $this->debugEntries = [];
    }

    public function render(): View
    {
        return view('browser-console::livewire.browser-console')
            ->layout('browser-console::layouts.terminal');
    }

    private function runArtisanCommand(string $input): void
    {
        // Strip "php artisan " prefix if typed
        $input = Str::after($input, 'php artisan ');
        $input = Str::after($input, 'artisan ');

        // Block `tinker` (and any tinker:* variant) — it runs arbitrary PHP via
        // --execute=, fully bypassing the shell-mode allowlist/denylist. Artisan
        // mode must not become an unrestricted code-execution surface.
        $base = strtolower(Str::before(trim($input), ' '));
        if (str_starts_with($base, 'tinker')) {
            $this->history[] = [
                'command' => $input,
                'output' => "Command 'tinker' is disabled in the browser console.",
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'artisan',
            ];

            return;
        }

        // Reject shell quotes in artisan mode — artisan command names/flags in
        // the curated reference set never legitimately need them, and they are
        // the vector for --execute=/argument-injection style eval payloads.
        if (str_contains($input, '"') || str_contains($input, "'")) {
            $this->history[] = [
                'command' => $input,
                'output' => 'Quotes (" \') are not allowed in artisan mode for security reasons.',
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'artisan',
            ];

            return;
        }

        // Block shell operators to prevent injection — artisan mode should
        // only run artisan commands, never arbitrary shell via ; && || etc.
        $shellCheck = $this->rejectShellOperators($input);
        if ($shellCheck !== true) {
            $this->history[] = [
                'command' => $input,
                'output' => $shellCheck,
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'artisan',
            ];

            return;
        }

        // Run as a subprocess to completely isolate from the web PHP process.
        // This prevents any stray PHP output from corrupting Livewire's JSON response.
        $start = microtime(true);
        $exitCode = null;
        try {
            $command = 'php artisan '.$input.' --no-ansi --no-interaction';

            $process = Process::fromShellCommandline($command, base_path());
            $process->setTimeout(120);
            $process->run();

            $output = trim($process->getOutput());

            // Include stderr if present (e.g. error messages)
            $stderr = trim($process->getErrorOutput());
            if ($stderr) {
                $output = $output ? $output."\n".$stderr : $stderr;
            }

            $output = $this->sanitizeOutput($output);

            // Truncate extremely large output (e.g. route:list)
            if (strlen($output) > 51200) {
                $output = mb_strcut($output, 0, 51200, 'UTF-8')."\n\n... [Output truncated at 50KB]";
            }

            $exitCode = $process->getExitCode();
            $this->history[] = [
                'command' => $input,
                'output' => $output ?: '(no output)',
                'status' => $process->isSuccessful() ? 'success' : 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'artisan',
            ];
        } catch (ProcessTimedOutException) {
            $exitCode = -2; // sentinel for timeout
            $this->history[] = [
                'command' => $input,
                'output' => 'Command timed out after 120 seconds.',
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'artisan',
            ];
        } catch (\Throwable $e) {
            $exitCode = -1; // sentinel for exception
            $this->history[] = [
                'command' => $input,
                'output' => 'Error: '.$this->sanitizeOutput($e->getMessage()),
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'artisan',
            ];
        }

        $this->auditCommandExecuted('artisan', $input, $exitCode, $start);
    }

    private function runShellCommand(string $input): void
    {
        if (! $this->shellAvailable) {
            $this->history[] = [
                'command' => $input,
                'output' => 'Shell execution is not available on this server. The proc_open() function is disabled.',
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'shell',
            ];

            return;
        }

        $validation = $this->validateShellCommand($input);

        if ($validation !== true) {
            $this->history[] = [
                'command' => $input,
                'output' => $validation,
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'shell',
            ];

            return;
        }

        $start = microtime(true);
        $exitCode = null;
        $originalInput = $input;
        try {
            $timeout = $this->getShellTimeout($input);

            // Give PHP more time than the process timeout
            if (function_exists('set_time_limit')) {
                set_time_limit($timeout + 10);
            }

            // Auto-append --no-interaction for composer
            if (str_starts_with($input, 'composer') && ! str_contains($input, '--no-interaction') && ! str_contains($input, '-n')) {
                $input .= ' --no-interaction';
            }

            $process = Process::fromShellCommandline($input, base_path());
            $process->setTimeout($timeout);

            // Extend PATH with common binary locations so subprocesses (git, node, etc.)
            // are discoverable even when PHP-FPM has a minimal PATH.
            $extraPaths = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
            $currentPath = getenv('PATH') ?: '';
            $process->setEnv([
                'GIT_TERMINAL_PROMPT' => '0',
                'COMPOSER_NO_INTERACTION' => '1',
                'PATH' => $currentPath ? $currentPath.':'.$extraPaths : $extraPaths,
            ]);

            // Stream output in real-time using Livewire's streaming
            $outputBuffer = '';

            $process->start(function (string $type, string $buffer) use (&$outputBuffer): void {
                $outputBuffer .= $buffer;

                // Clean carriage returns from progress output (composer)
                $cleaned = preg_replace('/\r(?!\n)/', "\n", $buffer);

                $this->stream(
                    'console-output',
                    e($this->sanitizeOutput($cleaned)),
                );
            });

            $process->wait();

            $output = trim($outputBuffer);

            // Collapse multiple blank lines
            $output = preg_replace('/\r(?!\n)/', "\n", $output);
            $output = preg_replace('/\n{3,}/', "\n\n", $output);

            // Truncate extremely large output (>50KB)
            if (strlen($output) > 51200) {
                $output = mb_strcut($output, 0, 51200, 'UTF-8')."\n\n... [Output truncated at 50KB]";
            }

            $output = $this->sanitizeOutput($output);

            $exitCode = $process->getExitCode();
            $this->history[] = [
                'command' => $input,
                'output' => $output ?: '(no output)',
                'status' => $process->isSuccessful() ? 'success' : 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'shell',
            ];
        } catch (ProcessTimedOutException) {
            $exitCode = -2; // sentinel for timeout
            $this->history[] = [
                'command' => $input,
                'output' => "Command timed out after {$timeout} seconds.",
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'shell',
            ];
        } catch (\Throwable $e) {
            $exitCode = -1; // sentinel for exception
            $this->history[] = [
                'command' => $input,
                'output' => 'Error: '.$this->sanitizeOutput($e->getMessage()),
                'status' => 'error',
                'timestamp' => now()->format('H:i:s'),
                'mode' => 'shell',
            ];
        }

        $this->auditCommandExecuted('shell', $originalInput, $exitCode, $start);
    }

    private function auditCommandExecuted(string $mode, string $command, ?int $exitCode, float $startedAt): void
    {
        Audit::event('console.command.executed', [
            'mode' => $mode,
            'command' => $command,
            'exit_code' => $exitCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * Reject shell operators and control characters that could allow injection.
     * Shared by both artisan and shell modes.
     *
     * @return true|string True if clean, or error message string
     */
    private function rejectShellOperators(string $input): true|string
    {
        // Block control characters (newline injection, carriage return)
        if (preg_match('/[\x00-\x1f\x7f]/', $input)) {
            return 'Control characters are not allowed in commands.';
        }

        // Block shell operators to prevent chaining/injection
        $dangerousOperators = [';', '&&', '||', '|', '>', '>>', '<', '`', '$(', '${'];

        foreach ($dangerousOperators as $op) {
            if (str_contains($input, $op)) {
                return "Shell operator '{$op}' is not allowed for security reasons.";
            }
        }

        // Block shell variable expansion and globbing unconditionally —
        // backslash-escaped variants like \$HOME can still expand in some shells.
        if (preg_match('/\$\w/', $input) || str_contains($input, '~')) {
            return 'Shell variable expansion ($VAR, ~) is not allowed for security reasons.';
        }

        return true;
    }

    /**
     * Validate a shell command against security rules.
     *
     * @return true|string True if valid, or error message string
     */
    private function validateShellCommand(string $input): true|string
    {
        $operatorCheck = $this->rejectShellOperators($input);
        if ($operatorCheck !== true) {
            return $operatorCheck;
        }

        // Extract the base command (first word)
        $parts = preg_split('/\s+/', $input, 2);
        $baseCommand = $parts[0];

        // Validate against allowlist
        $allowedCommands = [
            'composer',
            'git',
            'php',
            'ls',
            'pwd',
            'whoami',
            'readlink',
            'cat',
            'mkdir',
            'chmod',
            'ln',
            'df',
            'du',
            'head',
            'tail',
            'wc',
            'find',
            'which',
        ];

        if (! in_array($baseCommand, $allowedCommands, true)) {
            return "Command '{$baseCommand}' is not in the allowed commands list.\nAllowed: ".implode(', ', $allowedCommands);
        }

        // Block dangerous subcommand patterns
        $dangerousPatterns = [
            '/\brm\b/i',                      // Any rm usage
            '/git\s+push/i',                   // git push
            '/git\s+reset\s+--hard/i',         // git destructive reset
            '/git\s+clean/i',                  // git clean
            '/\.\.\//i',                       // Any ../ directory traversal
            '/\/etc\//i',                      // System config access
            '/\/root/i',                       // Root directory
            '/\/proc\//i',                     // Proc filesystem
            '/\/sys\//i',                      // Sys filesystem
            '/chmod\s+777/i',                  // Overly permissive
            '/php\s+-r/i',                     // PHP inline execution
            '/php\s+\S+\.php/i',              // PHP file execution
            '/composer\s+global/i',            // Global composer operations
            '/composer\s+create-project/i',    // Creating projects
            '/composer\s+exec/i',             // Composer exec
            '/composer\s+run/i',              // Composer scripts
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return 'This command contains a blocked pattern for security reasons.';
            }
        }

        return true;
    }

    private function getShellTimeout(string $input): int
    {
        // Composer operations can be very slow on shared hosting
        if (str_starts_with($input, 'composer')) {
            return 300;
        }

        // Git pull/clone can be slow
        if (str_starts_with($input, 'git pull') || str_starts_with($input, 'git clone')) {
            return 120;
        }

        // php -i generates lots of output
        if (str_starts_with($input, 'php -i')) {
            return 30;
        }

        return 60;
    }

    /**
     * Sanitize command output for safe JSON serialization.
     *
     * Strips ANSI escape codes, ensures valid UTF-8, and removes
     * control characters that would break Livewire's JSON response.
     */
    private function sanitizeOutput(string $output): string
    {
        // Strip ANSI escape codes (colors, cursor movement, etc.)
        $output = (string) preg_replace('/\x1B(?:\[[0-9;]*[A-Za-z]|\(B)/', '', $output);

        // Ensure valid UTF-8 (replace invalid sequences with ?)
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

        // Strip control characters except newline (\n), carriage return (\r), and tab (\t)
        $output = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $output);

        return $output;
    }

    /**
     * Read approximately the last N lines from a file without loading
     * the entire file into memory. Uses fseek to read from the tail.
     *
     * @return list<string>
     */
    private function readTailLines(string $path, int $lineCount): array
    {
        $fileSize = filesize($path);

        if ($fileSize === 0 || $fileSize === false) {
            return [];
        }

        // For small files (< 256KB), just read the whole thing — it's cheap
        if ($fileSize < 262144) {
            return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        }

        // For large files, read a chunk from the end.
        // Estimate ~500 bytes per line, add 20% buffer.
        $chunkSize = min($fileSize, (int) ($lineCount * 600));

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        fseek($handle, -$chunkSize, SEEK_END);

        // Discard the first partial line (we likely landed mid-line)
        fgets($handle);

        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        fclose($handle);

        return $lines;
    }
}
