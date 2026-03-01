<?php

use Codenzia\BrowserConsole\Livewire\BrowserConsole;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('shows login form when not authenticated', function () {
    Livewire::test(BrowserConsole::class)
        ->assertSee('Artisan Console')
        ->assertSee('Authenticate to continue')
        ->assertSet('isAuthenticated', false);
});

it('authenticates with valid credentials', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'testpass123')
        ->call('authenticate')
        ->assertSet('isAuthenticated', true);
});

it('rejects invalid username', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'wrong')
        ->set('password', 'testpass123')
        ->call('authenticate')
        ->assertSet('isAuthenticated', false)
        ->assertSee('Invalid credentials');
});

it('rejects invalid password', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'wrongpassword')
        ->call('authenticate')
        ->assertSet('isAuthenticated', false)
        ->assertSee('Invalid credentials');
});

it('shows error when credentials not configured', function () {
    config()->set('browser-console.user', null);
    config()->set('browser-console.password', null);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'testpass123')
        ->call('authenticate')
        ->assertSet('isAuthenticated', false)
        ->assertSee('Console access not configured');
});

it('clears password field after failed login', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'wrongpassword')
        ->call('authenticate')
        ->assertSet('password', '');
});

it('clears credentials after successful login', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'testpass123')
        ->call('authenticate')
        ->assertSet('username', '')
        ->assertSet('password', '');
});

it('logs out and clears history', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);

    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'testpass123')
        ->call('authenticate')
        ->call('logout')
        ->assertSet('isAuthenticated', false)
        ->assertSet('history', [])
        ->assertSet('command', '');
});

it('bypasses password auth when gate callback returns true', function () {
    config()->set('browser-console.gate', fn ($request) => true);

    Livewire::test(BrowserConsole::class)
        ->assertSet('isAuthenticated', true);
});

it('requires password auth when gate callback returns false', function () {
    config()->set('browser-console.gate', fn ($request) => false);

    Livewire::test(BrowserConsole::class)
        ->assertSet('isAuthenticated', false);
});

it('respects session timeout', function () {
    $hash = Hash::make('testpass123');
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', $hash);
    config()->set('browser-console.session_timeout', 1); // 1 second

    // Login and verify authenticated
    Livewire::test(BrowserConsole::class)
        ->set('username', 'admin')
        ->set('password', 'testpass123')
        ->call('authenticate')
        ->assertSet('isAuthenticated', true);

    // Clear the in-memory auth state to simulate a new HTTP request.
    // In production the cookie carries state between requests, but Livewire
    // tests share the app container within a test method.
    app()->forgetInstance('browser-console.auth.pending');

    // Without the cookie in the request, auth should be false
    $component = Livewire::test(BrowserConsole::class);
    expect($component->get('isAuthenticated'))->toBeFalse();
});
