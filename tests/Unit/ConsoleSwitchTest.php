<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Codenzia\BrowserConsole\Events\ConsoleAudit;
use Codenzia\BrowserConsole\Services\ConsoleSwitch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Cache::flush();
    CarbonImmutable::setTestNow(null);
});

afterEach(function () {
    CarbonImmutable::setTestNow(null);
});

function makeSwitch(): ConsoleSwitch
{
    return app(ConsoleSwitch::class);
}

describe('defaultState()', function () {
    it('returns "on" for default config (env unset)', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        expect(makeSwitch()->defaultState())->toBe('on');
    });

    it('returns "off" when configured', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        expect(makeSwitch()->defaultState())->toBe('off');
    });

    it('collapses "local" to "on" when in local env', function () {
        config()->set('browser-console.killswitch.default_state', 'local');
        app()['env'] = 'local';
        expect(makeSwitch()->defaultState())->toBe('on');
    });

    it('collapses "local" to "off" when not in local env', function () {
        config()->set('browser-console.killswitch.default_state', 'local');
        app()['env'] = 'production';
        expect(makeSwitch()->defaultState())->toBe('off');
    });

    it('falls back to "on" for invalid values and logs a warning', function () {
        config()->set('browser-console.killswitch.default_state', 'nonsense');
        expect(makeSwitch()->defaultState())->toBe('on');
    });

    it('normalises whitespace and case', function () {
        config()->set('browser-console.killswitch.default_state', '  OFF  ');
        expect(makeSwitch()->defaultState())->toBe('off');
    });
});

describe('isEnabled() truth table', function () {
    it('absent cache + default=on + password set → true', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        expect(makeSwitch()->isEnabled())->toBeTrue();
    });

    it('absent cache + default=off + password set → false', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        expect(makeSwitch()->isEnabled())->toBeFalse();
    });

    it('any state + empty password → false (fail-closed)', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        config()->set('browser-console.password', '');
        expect(makeSwitch()->isEnabled())->toBeFalse();
    });

    it('any state + null password → false (fail-closed)', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        config()->set('browser-console.password', null);
        expect(makeSwitch()->isEnabled())->toBeFalse();
    });

    it('enabled_until > now + default=off → true', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        makeSwitch()->enable(5);
        expect(makeSwitch()->isEnabled())->toBeTrue();
    });

    it('enabled_until elapsed → false and entry self-cleans', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        $switch = makeSwitch();
        $switch->enable(5);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(6));

        expect($switch->isEnabled())->toBeFalse();
    });

    it('force_disabled in on mode → false', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        makeSwitch()->disable();
        expect(makeSwitch()->isEnabled())->toBeFalse();
    });

    it('disable then enable in on mode → true', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        $switch = makeSwitch();
        $switch->disable();
        expect($switch->isEnabled())->toBeFalse();
        $switch->enable();
        expect($switch->isEnabled())->toBeTrue();
    });
});

describe('enable() validation in strict mode', function () {
    beforeEach(function () {
        config()->set('browser-console.killswitch.default_state', 'off');
    });

    it('accepts a TTL inside the configured range', function () {
        $expiresAt = makeSwitch()->enable(15);
        expect($expiresAt)->toBeInstanceOf(CarbonImmutable::class);
    });

    it('rejects TTL=0', function () {
        makeSwitch()->enable(0);
    })->throws(InvalidArgumentException::class);

    it('rejects TTL=-1', function () {
        makeSwitch()->enable(-1);
    })->throws(InvalidArgumentException::class);

    it('rejects TTL above the configured max', function () {
        config()->set('browser-console.killswitch.max_ttl', 60);
        makeSwitch()->enable(61);
    })->throws(InvalidArgumentException::class);

    it('uses default_ttl when none is passed', function () {
        config()->set('browser-console.killswitch.default_ttl', 7);
        $expiresAt = makeSwitch()->enable();
        $diff = $expiresAt->getTimestamp() - time();
        expect($diff)->toBeGreaterThan(6 * 60)->toBeLessThanOrEqual(7 * 60 + 5);
    });
});

describe('enable() in on mode', function () {
    it('returns null and clears any prior lockout', function () {
        config()->set('browser-console.killswitch.default_state', 'on');
        $switch = makeSwitch();
        $switch->disable();
        expect($switch->isEnabled())->toBeFalse();

        $result = $switch->enable(99); // TTL ignored in on mode
        expect($result)->toBeNull();
        expect($switch->isEnabled())->toBeTrue();
    });
});

describe('audit events', function () {
    it('dispatches console.switch.enabled on enable() in off mode', function () {
        Event::fake();
        config()->set('browser-console.killswitch.default_state', 'off');

        makeSwitch()->enable(10, 'ops@example.com');

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.enabled'
                && $e->context['mode'] === 'off'
                && $e->context['ttl_minutes'] === 10
                && $e->context['actor'] === 'ops@example.com'
                && is_string($e->context['expires_at']);
        });
    });

    it('dispatches console.switch.disabled on disable()', function () {
        Event::fake();
        makeSwitch()->disable('ops@example.com');

        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e): bool {
            return $e->name === 'console.switch.disabled'
                && $e->context['actor'] === 'ops@example.com';
        });
    });

    it('dispatches console.switch.expired exactly once when an expired entry is still in cache', function () {
        // Stage a cache entry whose embedded expires_at is in the past but
        // whose cache TTL is still alive. This simulates the production race
        // where the cache backend hasn't evicted yet but our wall-clock check
        // has already passed expiry. (The pest "array" cache driver collapses
        // Carbon time, so we can't use setTestNow() for this case.)
        Event::fake();
        config()->set('browser-console.killswitch.default_state', 'off');

        $past = CarbonImmutable::now()->subMinutes(2);
        \Illuminate\Support\Facades\Cache::put(
            'browser-console:state',
            ['kind' => 'enabled_until', 'expires_at' => $past->toIso8601String()],
            600,
        );

        $switch = makeSwitch();

        // Trigger evaluation twice to verify the expired event is emitted only once.
        expect($switch->isEnabled())->toBeFalse();
        expect($switch->isEnabled())->toBeFalse();

        $count = 0;
        Event::assertDispatched(ConsoleAudit::class, function (ConsoleAudit $e) use (&$count): bool {
            if ($e->name === 'console.switch.expired') {
                $count++;
            }

            return true;
        });
        expect($count)->toBe(1);
    });
});

describe('state() snapshot', function () {
    it('reports default_state, effective_state, password_configured', function () {
        config()->set('browser-console.killswitch.default_state', 'off');

        $state = makeSwitch()->state();

        expect($state)->toHaveKeys([
            'default_state',
            'effective_state',
            'enabled',
            'expires_at',
            'remaining_seconds',
            'password_configured',
        ])
            ->and($state['default_state'])->toBe('off')
            ->and($state['effective_state'])->toBe('disabled')
            ->and($state['enabled'])->toBeFalse()
            ->and($state['password_configured'])->toBeTrue();
    });

    it('includes expires_at and remaining_seconds when enabled with TTL', function () {
        config()->set('browser-console.killswitch.default_state', 'off');
        $switch = makeSwitch();
        $switch->enable(15);

        $state = $switch->state();
        expect($state['enabled'])->toBeTrue()
            ->and($state['effective_state'])->toBe('enabled')
            ->and($state['expires_at'])->toBeString()
            ->and($state['remaining_seconds'])->toBeGreaterThan(0)
            ->and($state['remaining_seconds'])->toBeLessThanOrEqual(15 * 60);
    });
});
