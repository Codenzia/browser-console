<?php

use Illuminate\Support\Facades\Hash;

it('create command is registered', function () {
    $this->artisan('browser-console:create')
        ->expectsQuestion('Enter console username', 'admin')
        ->expectsQuestion('Enter console password (min 8 characters)', 'password123')
        ->expectsQuestion('Confirm console password', 'password123')
        ->assertSuccessful();
});

it('create command fails with empty username', function () {
    $this->artisan('browser-console:create')
        ->expectsQuestion('Enter console username', '')
        ->assertFailed();
});

it('create command fails with short password', function () {
    $this->artisan('browser-console:create')
        ->expectsQuestion('Enter console username', 'admin')
        ->expectsQuestion('Enter console password (min 8 characters)', 'short')
        ->assertFailed();
});

it('create command fails when passwords do not match', function () {
    $this->artisan('browser-console:create')
        ->expectsQuestion('Enter console username', 'admin')
        ->expectsQuestion('Enter console password (min 8 characters)', 'password123')
        ->expectsQuestion('Confirm console password', 'different123')
        ->assertFailed();
});

it('create command writes credentials to env file', function () {
    // Create a temp .env file
    $envPath = app()->environmentFilePath();
    file_put_contents($envPath, "APP_NAME=TestApp\n");

    $this->artisan('browser-console:create')
        ->expectsQuestion('Enter console username', 'testadmin')
        ->expectsQuestion('Enter console password (min 8 characters)', 'password123')
        ->expectsQuestion('Confirm console password', 'password123')
        ->assertSuccessful();

    $envContent = file_get_contents($envPath);

    expect($envContent)->toContain('BROWSER_CONSOLE_USER=testadmin')
        ->and($envContent)->toContain('BROWSER_CONSOLE_PASSWORD=');

    // Clean up
    file_put_contents($envPath, '');
});

it('show command displays username', function () {
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', Hash::make('password123'));

    $this->artisan('browser-console:show')
        ->expectsOutputToContain('admin')
        ->assertSuccessful();
});

it('show command warns when no credentials configured', function () {
    config()->set('browser-console.user', null);
    config()->set('browser-console.password', null);

    $this->artisan('browser-console:show')
        ->expectsOutputToContain('No console access configured')
        ->assertSuccessful();
});

it('show command verifies correct password', function () {
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', Hash::make('password123'));

    $this->artisan('browser-console:show --verify')
        ->expectsQuestion('Enter password to verify', 'password123')
        ->expectsOutputToContain('correct')
        ->assertSuccessful();
});

it('show command rejects incorrect password', function () {
    config()->set('browser-console.user', 'admin');
    config()->set('browser-console.password', Hash::make('password123'));

    $this->artisan('browser-console:show --verify')
        ->expectsQuestion('Enter password to verify', 'wrongpassword')
        ->expectsOutputToContain('incorrect')
        ->assertFailed();
});
