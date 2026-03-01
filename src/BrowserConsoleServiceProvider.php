<?php

declare(strict_types=1);

namespace Codenzia\BrowserConsole;

use Codenzia\BrowserConsole\Http\Middleware\ForceFileSession;
use Illuminate\Routing\Router;
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
                        $cmd->newLine();

                        if ($cmd->confirm('Publish the diagnostics page to public/bcd.php?', true)) {
                            $source = __DIR__ . '/../stubs/bcd.php';
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
    }

    public function packageBooted(): void
    {
        // Register the Livewire component
        Livewire::component('browser-console::terminal', \Codenzia\BrowserConsole\Livewire\BrowserConsole::class);

        // Register routes
        $this->registerRoutes();

        // Prepend ForceFileSession to the web middleware group so it runs
        // before session start for console requests (including Livewire updates).
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->prependMiddlewareToGroup('web', ForceFileSession::class);

        // Guarantee execution order: ForceFileSession must run before
        // EncryptCookies / StartSession regardless of Laravel's middleware
        // priority sorting, which could otherwise reorder group middleware.
        array_unshift($router->middlewarePriority, ForceFileSession::class);
    }

    protected function registerRoutes(): void
    {
        Route::middleware([ForceFileSession::class, 'web'])
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
    }

    protected function loadHelpers(): void
    {
        $helperPath = __DIR__ . '/Helpers/helpers.php';

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
        ];
    }
}
