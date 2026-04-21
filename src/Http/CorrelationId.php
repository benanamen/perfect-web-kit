<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * Per-request correlation identifier.
 *
 * The contract is simple: every inbound HTTP request gets exactly one
 * correlation id, either pulled from a trusted upstream header
 * (`X-Request-ID` or `X-Correlation-ID`) or generated fresh as a RFC-4122
 * v4 UUID. The value is captured once via {@see self::acquire()} and held
 * in a process-local static so downstream code (loggers, response
 * middleware, outbound HTTP clients) can read the same id without threading
 * it through every call site.
 *
 * The class never reads superglobals on its own; callers pass a
 * `$_SERVER`-shaped array. That keeps it pure and testable.
 */
final class CorrelationId
{
    private const HEADER_REQUEST_ID = 'HTTP_X_REQUEST_ID';
    private const HEADER_CORRELATION_ID = 'HTTP_X_CORRELATION_ID';
    private const MAX_UPSTREAM_LENGTH = 128;

    private static ?string $current = null;

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $server A snapshot of `$_SERVER` (or any
     *                                     equivalent map of CGI variables).
     * @return string Always a non-empty correlation id.
     */
    public static function acquire(array $server): string
    {
        $existing = self::pickUpstream($server);
        self::$current = $existing ?? self::generateUuidV4();

        return self::$current;
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function pickUpstream(array $server): ?string
    {
        foreach ([self::HEADER_REQUEST_ID, self::HEADER_CORRELATION_ID] as $key) {
            $raw = $server[$key] ?? null;
            if (!is_string($raw)) {
                continue;
            }
            $trimmed = trim($raw);
            if ($trimmed === '') {
                continue;
            }
            if (strlen($trimmed) > self::MAX_UPSTREAM_LENGTH) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9._\-]+$/', $trimmed) !== 1) {
                continue;
            }

            return $trimmed;
        }

        return null;
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
