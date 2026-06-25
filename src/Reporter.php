<?php

declare(strict_types=1);

namespace G8Scope\WordPress;

use ErrorException;
use G8Scope\Php\Client;
use G8Scope\Php\Config;
use G8Scope\Php\Transport\Transport;
use Throwable;

/**
 * WordPress reporter: a thin wrapper over the runtime g8scope/php client.
 *
 * Captures thrown exceptions and PHP fatal errors (via error_get_last during
 * shutdown), enriches them with request context, and ships them. Every public
 * entry point is guarded so it can NEVER break the host site.
 */
final class Reporter
{
    private Client $client;

    /**
     * @param  array<string,mixed>  $options  Raw options mapped to G8Scope\Php\Config.
     */
    public function __construct(array $options, ?Transport $transport = null, ?Client $client = null)
    {
        $config = Config::fromArray($options);
        $this->client = $client ?? new Client($config, $transport);
    }

    /** @param array<string,mixed> $options */
    public static function fromOptions(array $options, ?Transport $transport = null): self
    {
        return new self($options, $transport);
    }

    public function captureThrowable(Throwable $e): void
    {
        try {
            $envelope = $this->client->buildEnvelope($e, $this->context());
            if ($envelope !== null) {
                $this->client->sendEnvelope($envelope);
            }
        } catch (Throwable) {
            // Never break the site.
        }
    }

    /**
     * Turn an error_get_last()-shaped array into a shipped envelope. Only the
     * fatal error classes are reported; warnings/notices are ignored.
     *
     * @param  array<string,mixed>|null  $error
     */
    public function captureFatal(?array $error): void
    {
        try {
            if (! is_array($error) || ! isset($error['type'])) {
                return;
            }

            if (! self::isFatal((int) $error['type'])) {
                return;
            }

            $exception = new ErrorException(
                (string) ($error['message'] ?? 'Fatal error'),
                0,
                (int) $error['type'],
                (string) ($error['file'] ?? '[unknown]'),
                (int) ($error['line'] ?? 0),
            );

            $envelope = $this->client->buildEnvelope($exception, $this->context());
            if ($envelope === null) {
                return;
            }

            // A fatal error means the script is dying anyway — mark it as such.
            $envelope['level'] = 'fatal';

            $this->client->sendEnvelope($envelope);
        } catch (Throwable) {
            // Never break the site.
        }
    }

    private static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    }

    /** @return array<string,mixed> */
    private function context(): array
    {
        return Context\RequestContext::capture();
    }
}
