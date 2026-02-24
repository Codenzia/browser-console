<?php

use Codenzia\BrowserConsole\Livewire\BrowserConsole;

/**
 * Test the shell command validation logic via reflection,
 * since validateShellCommand is a private method.
 */
beforeEach(function () {
    $this->component = new BrowserConsole();

    // Use reflection to access private method
    $this->validator = new ReflectionMethod(BrowserConsole::class, 'validateShellCommand');
    $this->validator->setAccessible(true);
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
