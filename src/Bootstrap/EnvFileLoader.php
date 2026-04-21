<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Bootstrap;

/**
 * Parses a dotenv-style file into an {@see EnvConfig}.
 *
 * Deliberately does not touch {@see \$_ENV}, call {@see putenv()}, or register any
 * side effects. The application receives values explicitly through the returned
 * {@see EnvConfig} and passes them to services via constructor injection.
 *
 * Supported syntax:
 *  - Blank lines and lines beginning with `#` are ignored.
 *  - Key names must match [A-Z_][A-Z0-9_]* (any case).
 *  - Values may be quoted with single or double quotes; quotes are stripped.
 *  - Values without quotes are trimmed.
 *  - `\n`, `\r`, `\t`, `\\`, `\"`, `\'` escape sequences are honored inside quoted values.
 */
final class EnvFileLoader
{
    public function loadFromFile(string $path): EnvConfig
    {
        if (!is_file($path)) {
            return new EnvConfig([]);
        }
        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new \RuntimeException(\sprintf('Could not read environment file "%s".', $path));
        }

        return $this->loadFromString($raw);
    }

    public function loadFromString(string $raw): EnvConfig
    {
        $values = [];
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        if ($lines === false) {
            return new EnvConfig([]);
        }
        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $valuePart = substr($line, $eq + 1);
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }
            $values[$key] = self::parseValue($valuePart);
        }

        return new EnvConfig($values);
    }

    private static function parseValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $first = $trimmed[0];
        $last = $trimmed[strlen($trimmed) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $inner = substr($trimmed, 1, -1);
            if ($first === '"') {
                return self::applyEscapes($inner);
            }

            return $inner;
        }
        $hashPos = strpos($trimmed, ' #');
        if ($hashPos !== false) {
            $trimmed = rtrim(substr($trimmed, 0, $hashPos));
        }

        return $trimmed;
    }

    private static function applyEscapes(string $value): string
    {
        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\"' => '"',
            "\\'" => "'",
            '\\\\' => '\\',
        ]);
    }
}
