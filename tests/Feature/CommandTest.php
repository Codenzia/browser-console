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

it('diagnose command runs successfully', function () {
    $this->artisan('browser-console:diagnose')
        ->expectsOutputToContain('Deployment Diagnostics')
        ->assertExitCode(0);
});

it('diagnose command registers the diagnose command', function () {
    expect(array_keys(\Artisan::all()))
        ->toContain('browser-console:diagnose');
});

it('diagnose --refresh copies bcd.php to public', function () {
    $destination = public_path('bcd.php');

    // Clean up if leftover
    if (file_exists($destination)) {
        unlink($destination);
    }

    $this->artisan('browser-console:diagnose --refresh')
        ->expectsOutputToContain('refreshed')
        ->assertSuccessful();

    expect(file_exists($destination))->toBeTrue();

    // Clean up
    unlink($destination);
});

it('diagnose --refresh overwrites existing bcd.php', function () {
    $destination = public_path('bcd.php');

    if (! is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0755, true);
    }
    file_put_contents($destination, '<?php // old version');

    $this->artisan('browser-console:diagnose --refresh')
        ->assertSuccessful();

    expect(file_get_contents($destination))->not->toBe('<?php // old version');

    // Clean up
    unlink($destination);
});

it('diagnose --remove deletes bcd.php from public', function () {
    $destination = public_path('bcd.php');

    if (! is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0755, true);
    }
    file_put_contents($destination, '<?php // diagnostics');

    $this->artisan('browser-console:diagnose --remove')
        ->expectsOutputToContain('removed')
        ->assertSuccessful();

    expect(file_exists($destination))->toBeFalse();
});

it('diagnose --remove handles missing file gracefully', function () {
    $destination = public_path('bcd.php');

    if (file_exists($destination)) {
        unlink($destination);
    }

    $this->artisan('browser-console:diagnose --remove')
        ->expectsOutputToContain('not found')
        ->assertSuccessful();
});
