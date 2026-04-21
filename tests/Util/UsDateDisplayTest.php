<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Util;

use PerfectApp\WebKit\Util\UsDateDisplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsDateDisplay::class)]
final class UsDateDisplayTest extends TestCase
{
    public function testFormatUsDate(): void
    {
        $this->assertSame('03-31-2026', UsDateDisplay::formatUsDate('2026-03-31'));
        $this->assertSame('', UsDateDisplay::formatUsDate(null));
        $this->assertSame('', UsDateDisplay::formatUsDate('   '));
        $this->assertSame('garbage', UsDateDisplay::formatUsDate('garbage'));
    }

    public function testFormatDdMmYyyy(): void
    {
        $this->assertSame('31-03-2026', UsDateDisplay::formatDdMmYyyy('2026-03-31'));
        $this->assertSame('', UsDateDisplay::formatDdMmYyyy(''));
    }

    public function testNaiveMidnightStaysCivilDate(): void
    {
        $this->assertSame('03-31-2026', UsDateDisplay::formatUsDateTime('2026-03-31 00:00:00'));
    }

    public function testBareDateStaysCivilDate(): void
    {
        $this->assertSame('03-31-2026', UsDateDisplay::formatUsDateTime('2026-03-31'));
    }

    public function testDisplayTimezoneAppliedForZuluInput(): void
    {
        $out = UsDateDisplay::formatUsDateTime('2026-03-31T20:00:00Z', 'America/New_York');
        $this->assertSame('03-31-2026 4:00 PM', $out);
    }

    public function testEmDashPassesThrough(): void
    {
        $this->assertSame('—', UsDateDisplay::formatUsDateTime('—'));
    }
}
