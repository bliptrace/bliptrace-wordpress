<?php

declare(strict_types=1);

namespace Bliptrace\WordPress\Context;

/**
 * Builds the envelope context from the current request server vars.
 *
 * Pure PHP and fully testable: pass an array (defaults to $_SERVER). Sensitive
 * headers (authorization, cookie) are stripped before anything is shipped.
 */
final class RequestContext
{
    /**
     * @param  array<string,mixed>|null  $server
     * @return array<string,mixed>
     */
    public static function capture(?array $server = null): array
    {
        try {
            $server ??= $_SERVER ?? [];

            $context = ['custom' => []];

            $method = $server['REQUEST_METHOD'] ?? null;
            if ($method === null) {
                return $context;
            }

            $context['request'] = [
                'method' => (string) $method,
                'url' => self::url($server),
                'headers' => self::headers($server),
            ];

            return $context;
        } catch (\Throwable) {
            return ['custom' => []];
        }
    }

    /** @param array<string,mixed> $server */
    private static function url(array $server): string
    {
        $https = ($server['HTTPS'] ?? '') !== '' && $server['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');
        $uri = (string) ($server['REQUEST_URI'] ?? '/');

        return $scheme.'://'.$host.$uri;
    }

    /**
     * Reconstruct request headers from $_SERVER HTTP_* keys, dropping
     * authorization and cookie so secrets never leave the box.
     *
     * @param  array<string,mixed>  $server
     * @return array<string,string>
     */
    private static function headers(array $server): array
    {
        $skip = ['authorization', 'cookie'];
        $headers = [];

        foreach ($server as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));

            if (in_array(strtolower($name), $skip, true)) {
                continue;
            }

            $headers[$name] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        return $headers;
    }
}
