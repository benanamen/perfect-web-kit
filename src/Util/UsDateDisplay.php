<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Util;

/**
 * Formats API date and datetime strings for US-style display (mm-dd-YYYY, h:i A).
 *
 * Rules:
 *  - Bare Y-m-d dates are rearranged without any timezone math.
 *  - Naive civil datetimes (e.g. "2026-03-31 00:00:00") are treated as civil dates,
 *    not UTC midnight.
 *  - Datetimes with explicit timezone information honor it and are optionally
 *    converted to a caller-provided display timezone.
 *  - Unparseable inputs are returned unchanged.
 */
final class UsDateDisplay
{
    public static function formatUsDate(?string $isoYmd): string
    {
        $trimmed = trim((string) $isoYmd);
        if ($trimmed === '') {
            return '';
        }
        $parts = self::parseLeadingIsoYmd($trimmed);
        if ($parts === null) {
            return $trimmed;
        }

        return $parts['m'] . '-' . $parts['d'] . '-' . $parts['y'];
    }

    public static function formatDdMmYyyy(?string $isoYmd): string
    {
        $trimmed = trim((string) $isoYmd);
        if ($trimmed === '') {
            return '';
        }
        $parts = self::parseLeadingIsoYmd($trimmed);
        if ($parts === null) {
            return $trimmed;
        }

        return $parts['d'] . '-' . $parts['m'] . '-' . $parts['y'];
    }

    public static function formatUsDateTime(?string $value, ?string $displayTimezone = null): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '' || $trimmed === '—') {
            return $trimmed;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $match) === 1) {
            return $match[2] . '-' . $match[3] . '-' . $match[1];
        }
        if (self::isNaiveMidnightDateTime($trimmed) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $trimmed, $match) === 1) {
            return $match[2] . '-' . $match[3] . '-' . $match[1];
        }
        $tz = self::resolveTimezone($displayTimezone);
        try {
            $dt = self::stringHasTimeZoneInfo($trimmed)
                ? new \DateTimeImmutable($trimmed)
                : new \DateTimeImmutable($trimmed, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return $trimmed;
        }

        return $dt->setTimezone($tz)->format('m-d-Y g:i A');
    }

    /**
     * @return array{y: string, m: string, d: string}|null
     */
    private static function parseLeadingIsoYmd(string $value): ?array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match) !== 1) {
            return null;
        }

        return ['y' => $match[1], 'm' => $match[2], 'd' => $match[3]];
    }

    private static function isNaiveMidnightDateTime(string $value): bool
    {
        return (bool) preg_match('/^(\d{4}-\d{2}-\d{2})[ T]00:00:00(?:\.\d+)?$/', $value);
    }

    private static function stringHasTimeZoneInfo(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match('/[zZ]$|[+-]\d{2}:?\d{2}$/', $trimmed);
    }

    private static function resolveTimezone(?string $displayTimezone): \DateTimeZone
    {
        $id = $displayTimezone === null ? '' : trim($displayTimezone);
        if ($id === '') {
            $id = date_default_timezone_get();
        }
        try {
            return new \DateTimeZone($id);
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }
}
