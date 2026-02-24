<?php

arch('source files use strict types')
    ->expect('Codenzia\BrowserConsole')
    ->toUseStrictTypes();

arch('no debugging functions left in source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('service provider extends PackageServiceProvider')
    ->expect('Codenzia\BrowserConsole\BrowserConsoleServiceProvider')
    ->toExtend('Spatie\LaravelPackageTools\PackageServiceProvider');

arch('commands extend Illuminate Command')
    ->expect('Codenzia\BrowserConsole\Commands')
    ->toExtend('Illuminate\Console\Command');

arch('livewire component extends Component')
    ->expect('Codenzia\BrowserConsole\Livewire\BrowserConsole')
    ->toExtend('Livewire\Component');
