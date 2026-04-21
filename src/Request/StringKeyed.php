<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Request;

/**
 * Narrow arbitrary PHP arrays (including superglobal snapshots) to string-keyed maps.
 *
 * Callers should pass a caller-owned snapshot array; consumption of the global
 * superglobals happens in one place at the edge of the application (the front
 * controller) and nowhere else.
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
}
