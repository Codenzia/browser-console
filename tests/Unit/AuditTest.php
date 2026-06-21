<?php

declare(strict_types=1);

use Codenzia\BrowserConsole\Events\ConsoleAudit;
use Codenzia\BrowserConsole\Support\Audit;
use Illuminate\Support\Facades\Event;

describe('Audit::event()', function () {
    it('dispatches a ConsoleAudit event with the given name and context', function () {
        Event::fake();

        Audit::event('console.test.example', ['key' => 'value']);

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.test.example'
                && $e->context === ['key' => 'value'];
        });
    });

    it('is a no-op when audit.enabled is false', function () {
        Event::fake();
        config()->set('browser-console.audit.enabled', false);

        Audit::event('console.test.example', ['key' => 'value']);

        Event::assertNotDispatched(ConsoleAudit::class);
    });

    it('scrubs sensitive keys from context before dispatch', function () {
        Event::fake();

        Audit::event('console.test.example', [
            'safe' => 'ok',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'output' => 'never log me',
            'output_body' => 'or me',
            'secret' => 'nope',
        ]);

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return ! array_key_exists('password', $e->context)
                && ! array_key_exists('password_confirmation', $e->context)
                && ! array_key_exists('output', $e->context)
                && ! array_key_exists('output_body', $e->context)
                && ! array_key_exists('secret', $e->context)
                && ($e->context['safe'] ?? null) === 'ok';
        });
    });
});
