<?php

declare(strict_types=1);

use Bliptrace\WordPress\Context\RequestContext;

it('builds request context from server vars and strips sensitive headers', function (): void {
    $context = RequestContext::capture([
        'REQUEST_METHOD' => 'POST',
        'HTTPS' => 'on',
        'HTTP_HOST' => 'example.test',
        'REQUEST_URI' => '/wp-admin/post.php?action=edit',
        'HTTP_ACCEPT' => 'text/html',
        'HTTP_AUTHORIZATION' => 'Bearer secret',
        'HTTP_COOKIE' => 'session=abc',
    ]);

    expect($context['request']['method'])->toBe('POST')
        ->and($context['request']['url'])->toBe('https://example.test/wp-admin/post.php?action=edit')
        ->and($context['request']['headers'])->toHaveKey('Accept')
        ->and($context['request']['headers'])->not->toHaveKey('Authorization')
        ->and($context['request']['headers'])->not->toHaveKey('Cookie')
        ->and($context['custom'])->toBe([]);
});

it('yields only an empty custom map when there is no request', function (): void {
    expect(RequestContext::capture([]))->toBe(['custom' => []]);
});

it('builds an http url when not secure', function (): void {
    $context = RequestContext::capture([
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'plain.test',
        'REQUEST_URI' => '/',
    ]);

    expect($context['request']['url'])->toBe('http://plain.test/');
});

it('never throws', function (): void {
    expect(fn () => RequestContext::capture(['REQUEST_METHOD' => 'GET']))->not->toThrow(Throwable::class);
});
