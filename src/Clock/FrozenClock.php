<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Clock;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Test-only Clock that reports a fixed moment in time until advanced.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable|string $instant = 'now', ?DateTimeZone $timezone = null)
    {
        $tz = $timezone ?? new DateTimeZone('UTC');
        if ($instant instanceof DateTimeImmutable) {
            $this->now = $instant;

            return;
        }
        try {
            $this->now = new DateTimeImmutable($instant, $tz);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'FrozenClock instant must be a valid date expression: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function timestamp(): int
    {
        return $this->now->getTimestamp();
    }

    /**
     * Move the clock forward by $seconds. Negative values rewind.
     */
    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }

    public function set(DateTimeImmutable $instant): void
    {
        $this->now = $instant;
    }
}
