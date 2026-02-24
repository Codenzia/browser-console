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
                    ->askToStarRepoOnGitHub('codenzia/browser-console');
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
        // before session start for console requests.
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->prependMiddlewareToGroup('web', ForceFileSession::class);
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
        ];
    }
}
