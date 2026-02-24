<?php

namespace Codenzia\BrowserConsole\Tests;

use Codenzia\BrowserConsole\BrowserConsoleServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BrowserConsoleServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('session.driver', 'file');
        config()->set('browser-console.user', 'testuser');
        config()->set('browser-console.password', '$2y$12$BxjppjcpMnSeq34CRScXV.RIkzsJYJuqPr17JKXD9n3GZluM4thIy');
        config()->set('browser-console.path', 'console');
        config()->set('browser-console.throttle', 0);
        config()->set('browser-console.session_timeout', 1800);
    }
}
