<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Request;

/**
 * Narrow arbitrary PHP arrays (including superglobal snapshots) to string-keyed maps.
 *
 * Two entry points:
 *
 *  - {@see self::fromArray()} / {@see self::fromGlobalsArray()} narrow a caller-owned snapshot.
 *  - {@see self::post()} / {@see self::get()} / {@see self::server()} are the single authorized
 *    readers of the matching superglobals in the whole application. Every other file should
 *    either depend on a snapshot or call these helpers once at the edge of the request.
 */
final class StringKeyed
{
    /**
     * @param array<mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function fromArray(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function fromGlobalsArray(array $row): array
    {
        return self::fromArray($row);
    }

    /**
     * @return array<string, mixed>
     */
    public static function post(): array
    {
        return self::fromArray($_POST);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        return self::fromArray($_GET);
    }

    /**
     * @return array<string, mixed>
     */
    public static function server(): array
    {
        return self::fromArray($_SERVER);
    }
}
