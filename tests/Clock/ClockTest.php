<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Clock;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PerfectApp\WebKit\Clock\FrozenClock;
use PerfectApp\WebKit\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testSystemClockNowReturnsCurrentTimeWithinTolerance(): void
    {
        $clock = new SystemClock(new DateTimeZone('UTC'));
        $before = time();
        $now = $clock->now();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $now->getTimestamp());
        $this->assertLessThanOrEqual($after, $now->getTimestamp());
        $this->assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testSystemClockTimestampMatchesNow(): void
    {
        $clock = new SystemClock();
        $ts = $clock->timestamp();
        $this->assertLessThanOrEqual(1, abs(time() - $ts));
    }

    public function testFrozenClockReturnsFixedInstant(): void
    {
        $clock = new FrozenClock('2026-04-21T12:00:00+00:00');
        $this->assertSame('2026-04-21T12:00:00+00:00', $clock->now()->format(DATE_ATOM));
        $this->assertSame($clock->now()->getTimestamp(), $clock->timestamp());

        $clock->now();
        usleep(1000);
        $this->assertSame('2026-04-21T12:00:00+00:00', $clock->now()->format(DATE_ATOM));
    }

    public function testFrozenClockAcceptsDateTimeImmutable(): void
    {
        $instant = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $clock = new FrozenClock($instant);
        $this->assertSame($instant->getTimestamp(), $clock->timestamp());
    }

    public function testFrozenClockAdvance(): void
    {
        $clock = new FrozenClock('2026-04-21T00:00:00+00:00');
        $clock->advance(90);
        $this->assertSame('2026-04-21T00:01:30+00:00', $clock->now()->format(DATE_ATOM));

        $clock->advance(-30);
        $this->assertSame('2026-04-21T00:01:00+00:00', $clock->now()->format(DATE_ATOM));
    }

    public function testFrozenClockSet(): void
    {
        $clock = new FrozenClock('2026-04-21T00:00:00+00:00');
        $clock->set(new DateTimeImmutable('2030-12-31T23:59:59+00:00'));
        $this->assertSame('2030-12-31T23:59:59+00:00', $clock->now()->format(DATE_ATOM));
    }

    public function testFrozenClockRejectsInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FrozenClock('not a real date');
    }
}
