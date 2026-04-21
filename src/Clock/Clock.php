<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Clock;

use DateTimeImmutable;

/**
 * Abstraction over the current moment in time.
 *
 * Code that needs "now" should depend on this interface instead of calling
 * new DateTimeImmutable('now') / time() directly. Tests can inject a
 * FrozenClock to produce deterministic output.
 */
interface Clock
{
    public function now(): DateTimeImmutable;

    /**
     * Unix timestamp in seconds. Equivalent to ::now()->getTimestamp().
     */
    public function timestamp(): int;
}
