<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole;

use Codenzia\BrowserConsole\Http\Middleware\ConsoleEnabled;
use Codenzia\BrowserConsole\Http\Middleware\ConsoleGate;
use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Codenzia\BrowserConsole\Services\ConsoleSwitch;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BrowserConsoleServiceProvider extends PackageServiceProvider
{
    public static string $name = 'browser-console';

    public static string $viewNamespace = 'browser-console';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('codenzia/browser-console')
                    ->endWith(function (InstallCommand $cmd) {
                        $cmd->newLine();
                        $cmd->warn('  Diagnostics page (public/bcd.php)');
                        $cmd->line('  This publishes a standalone diagnostics page for troubleshooting');
                        $cmd->line('  deployment issues when Laravel cannot boot.');
                        $cmd->line('  It is protected by your BROWSER_CONSOLE_PASSWORD.');
                        $cmd->warn('  It exposes server info (PHP version, extensions, permissions).');
                        $cmd->warn('  It is NOT governed by the kill switch — remove it before production.');
                        $cmd->newLine();

                        if ($cmd->confirm('Publish the diagnostics page to public/bcd.php?', false)) {
                            $source = __DIR__.'/../stubs/bcd.php';
                            $destination = public_path('bcd.php');

                            if (file_exists($source)) {
                                copy($source, $destination);
                                $cmd->info('  Diagnostics page published to public/bcd.php');
                                $cmd->line('  You can remove it later: php artisan browser-console:diagnose --remove');
                            }
                        } else {
                            $cmd->line('  Skipped. You can publish it later: php artisan browser-console:diagnose --refresh');
                        }
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->loadHelpers();

        $this->app->singleton('console.switch', function ($app): ConsoleSwitch {
            return new ConsoleSwitch(
                $app->make(CacheRepository::class),
                $app->make(Application::class),
            );
        });

        $this->app->alias('console.switch', ConsoleSwitch::class);
    }

    public function packageBooted(): void
    {
        // Register the Livewire component
        $components = [
            'browser-console::terminal' => BrowserConsole::class,
        ];

        foreach ($components as $alias => $class) {
            Livewire::component($alias, $class);
        }

        // Livewire v4's Finder only checks registered namespaces for
        // `ns::component` lookups, never classComponents entries.
        if (method_exists(Livewire::getFacadeRoot(), 'resolveMissingComponent')) {
            Livewire::resolveMissingComponent(fn (string $name): ?string => $components[$name] ?? null);
        }

        // Re-apply the kill-switch and IP/active gate to every Livewire
        // update so that disabling the console mid-session also kills any
        // in-flight terminal AJAX traffic. ConsoleEnabled runs first.
        Livewire::addPersistentMiddleware(ConsoleEnabled::class);
        Livewire::addPersistentMiddleware(ConsoleGate::class);

        // Register routes
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        // ConsoleEnabled MUST come before ConsoleGate so that a sealed route
        // 404s opaquely without exercising IP allowlist logic or the `web`
        // middleware stack. Order is load-bearing.
        Route::middleware([ConsoleEnabled::class, ConsoleGate::class, 'web'])
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
    }

    protected function loadHelpers(): void
    {
        $helperPath = __DIR__.'/Helpers/helpers.php';

        if (file_exists($helperPath)) {
            require_once $helperPath;
        }
    }

    protected function getCommands(): array
    {
        return [
            Commands\CreateAccessCommand::class,
            Commands\ShowAccessCommand::class,
            Commands\DiagnoseCommand::class,
            Commands\EnableCommand::class,
            Commands\DisableCommand::class,
            Commands\StatusCommand::class,
        ];
    }
}
