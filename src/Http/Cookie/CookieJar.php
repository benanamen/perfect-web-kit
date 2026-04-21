<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http\Cookie;

/**
 * Parses Set-Cookie response headers and rebuilds a single Cookie request header,
 * suitable for storing an upstream API session cookie in the local session.
 *
 * This is intentionally minimal: attributes like Domain, Path, Expires, Secure,
 * HttpOnly, and SameSite are ignored because the cookie is re-sent to the exact
 * same origin on the next request and carried in the same browser session.
 */
final class CookieJar
{
    /**
     * Extract raw Set-Cookie lines from a response header block.
     *
     * @return list<string>
     */
    public static function extractSetCookieLines(string $headerBlock): array
    {
        if ($headerBlock === '') {
            return [];
        }
        $split = preg_split("/\r\n|\n|\r/", $headerBlock);
        $lines = $split === false ? [] : $split;
        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^Set-Cookie:\s*(.+)$/i', $trimmed, $matches) === 1) {
                $out[] = trim($matches[1]);
            }
        }

        return $out;
    }

    /**
     * Combine Set-Cookie values into a single Cookie header, taking only the
     * "name=value" pair from each and discarding attributes.
     *
     * @param list<string> $setCookieValues each entry is a bare "name=value; ..." string
     */
    public static function toCookieHeader(array $setCookieValues): ?string
    {
        $pairs = [];
        foreach ($setCookieValues as $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $pair = trim(explode(';', $trimmed, 2)[0]);
            if ($pair !== '') {
                $pairs[] = $pair;
            }
        }

        return $pairs === [] ? null : implode('; ', $pairs);
    }
}
