<?php

declare(strict_types=1);

namespace Bliptrace\WordPress\Tests;

use Bliptrace\Php\Transport\Transport;

/** Records sent envelopes instead of hitting the network. */
final class FakeTransport implements Transport
{
    /** @var array<int,array<string,mixed>> */
    public array $sent = [];

    public function send(string $endpoint, string $key, array $envelope, int $timeout): void
    {
        $this->sent[] = compact('endpoint', 'key', 'envelope', 'timeout');
    }
}
