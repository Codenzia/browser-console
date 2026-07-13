<?php

// Load only the framework-free helper functions from the diagnostics stub. The
// harness constant makes bcd.php return right after declaring its helpers,
// skipping the stateful page (sessions, headers, HTML output).
if (! defined('BCD_TEST_HARNESS')) {
    define('BCD_TEST_HARNESS', true);
}

require_once dirname(__DIR__, 2).'/stubs/bcd.php';

beforeEach(function () {
    $this->file = sys_get_temp_dir().'/bcd-throttle-'.getmypid().'-'.uniqid().'.json';
    @unlink($this->file);
});

afterEach(function () {
    @unlink($this->file);
});

it('starts unlocked with a zero count', function () {
    $rec = bc_throttle_record(bc_throttle_load($this->file), '1.2.3.4', time(), 900);

    expect($rec['count'])->toBe(0)
        ->and(bc_throttle_locked($rec, 5))->toBeFalse();
});

it('locks out after the max failed attempts and survives a session reset (CON-002)', function () {
    $now = time();
    for ($i = 0; $i < 5; $i++) {
        bc_throttle_hit($this->file, '1.2.3.4', $now, 900, 5);
    }

    // A fresh load simulates a new request with no session cookie — the count is
    // still authoritative because it lives in the file store, not the session.
    $rec = bc_throttle_record(bc_throttle_load($this->file), '1.2.3.4', $now, 900);

    expect(bc_throttle_locked($rec, 5))->toBeTrue();
});

it('scopes the lockout per IP', function () {
    $now = time();
    for ($i = 0; $i < 5; $i++) {
        bc_throttle_hit($this->file, '1.2.3.4', $now, 900, 5);
    }

    $other = bc_throttle_record(bc_throttle_load($this->file), '9.9.9.9', $now, 900);

    expect(bc_throttle_locked($other, 5))->toBeFalse();
});

it('resets after the lockout window elapses', function () {
    $past = time() - 1000; // older than the 900s window
    for ($i = 0; $i < 5; $i++) {
        bc_throttle_hit($this->file, '1.2.3.4', $past, 900, 5);
    }

    $rec = bc_throttle_record(bc_throttle_load($this->file), '1.2.3.4', time(), 900);

    expect($rec['count'])->toBe(0)
        ->and(bc_throttle_locked($rec, 5))->toBeFalse();
});

it('clears the record on success', function () {
    $now = time();
    bc_throttle_hit($this->file, '1.2.3.4', $now, 900, 5);
    bc_throttle_clear($this->file, '1.2.3.4');

    $rec = bc_throttle_record(bc_throttle_load($this->file), '1.2.3.4', $now, 900);

    expect($rec['count'])->toBe(0);
});
