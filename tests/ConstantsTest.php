<?php

declare(strict_types=1);

// Constants cannot be undefined, so each case uses its own option.
define('G8SCOPE_TESTING', true);
define('BLIPTRACE_KEY', 'new-key');
define('G8SCOPE_KEY', 'old-key');
define('G8SCOPE_ENDPOINT', 'http://old');
define('BLIPTRACE_ENABLED', false);

require_once __DIR__.'/../g8scope.php';

it('reads BLIPTRACE_* constants, falls back to G8SCOPE_*, and leaves unset options alone', function (): void {
    $options = g8scope_resolve_options();

    expect($options['key'])->toBe('new-key')
        ->and($options['endpoint'])->toBe('http://old')
        ->and($options['enabled'])->toBeFalse()
        ->and($options)->not->toHaveKey('release')
        ->and($options)->not->toHaveKey('environment');
});
