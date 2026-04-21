<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Clock;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Production Clock implementation backed by the real system clock.
 */
final class SystemClock implements Clock
{
    private readonly DateTimeZone $timezone;

    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone('UTC');
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }

    public function timestamp(): int
    {
        return $this->now()->getTimestamp();
    }
}
