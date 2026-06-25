<?php

declare(strict_types=1);

use G8Scope\WordPress\Reporter;
use G8Scope\WordPress\Tests\FakeTransport;

it('captures a throwable into a valid, shipped envelope', function (): void {
    $t = new FakeTransport;
    $reporter = new Reporter([
        'key' => 'k',
        'endpoint' => 'http://api',
        'base_path' => '/srv/wp',
        'sdk_name' => 'g8scope-wordpress',
    ], $t);

    $reporter->captureThrowable(new RuntimeException('boom'));

    expect($t->sent)->toHaveCount(1);
    $env = $t->sent[0]['envelope'];

    expect($t->sent[0]['endpoint'])->toBe('http://api')
        ->and($t->sent[0]['key'])->toBe('k')
        ->and($env['schema_version'])->toBe('1.0')
        ->and($env['event_id'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($env['runtime']['name'])->toBe('php')
        ->and($env['sdk']['name'])->toBe('g8scope-wordpress')
        ->and($env['level'])->toBe('error')
        ->and($env['exception']['type'])->toBe('RuntimeException')
        ->and($env['exception']['message'])->toBe('boom');
});

it('turns an error_get_last array into a fatal envelope', function (): void {
    $t = new FakeTransport;
    $reporter = new Reporter(['key' => 'k', 'endpoint' => 'http://api'], $t);

    $reporter->captureFatal([
        'type' => E_ERROR,
        'message' => 'Allowed memory size exhausted',
        'file' => '/srv/wp/wp-content/plugins/bad/bad.php',
        'line' => 42,
    ]);

    expect($t->sent)->toHaveCount(1);
    $env = $t->sent[0]['envelope'];

    expect($env['level'])->toBe('fatal')
        ->and($env['exception']['type'])->toBe(ErrorException::class)
        ->and($env['exception']['message'])->toBe('Allowed memory size exhausted')
        ->and($env['exception']['frames'][0]['file'])->toBe('/srv/wp/wp-content/plugins/bad/bad.php')
        ->and($env['exception']['frames'][0]['line'])->toBe(42);
});

it('ignores non-fatal and empty error_get_last data', function (): void {
    $t = new FakeTransport;
    $reporter = new Reporter(['key' => 'k'], $t);

    $reporter->captureFatal(null);
    $reporter->captureFatal(['type' => E_WARNING, 'message' => 'just a warning']);
    $reporter->captureFatal([]);

    expect($t->sent)->toBeEmpty();
});

it('does nothing without a key', function (): void {
    $t = new FakeTransport;
    (new Reporter(['key' => null], $t))->captureThrowable(new Exception('x'));
    (new Reporter(['key' => 'k', 'enabled' => false], $t))->captureThrowable(new Exception('x'));

    expect($t->sent)->toBeEmpty();
});

it('never throws from capture entry points', function (): void {
    $t = new FakeTransport;
    $reporter = new Reporter(['key' => 'k'], $t);

    expect(fn () => $reporter->captureThrowable(new Exception('x')))->not->toThrow(Throwable::class)
        ->and(fn () => $reporter->captureFatal(['type' => 'not-an-int']))->not->toThrow(Throwable::class);
});
