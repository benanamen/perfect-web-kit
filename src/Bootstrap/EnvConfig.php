<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Bootstrap;

/**
 * Immutable, string-keyed environment configuration.
 *
 * {@see EnvFileLoader} produces instances of this class without mutating
 * $_ENV or calling putenv(). All lookups are explicit; missing keys raise
 * {@see self::required()} or return the provided default.
 */
final readonly class EnvConfig
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(private array $values)
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function string(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function required(string $key): string
    {
        if (!isset($this->values[$key]) || $this->values[$key] === '') {
            throw new \RuntimeException(\sprintf('Required environment variable "%s" is missing or empty.', $key));
        }

        return $this->values[$key];
    }

    public function bool(string $key, bool $default = false): bool
    {
        if (!isset($this->values[$key])) {
            return $default;
        }
        $value = strtolower($this->values[$key]);

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        if (!isset($this->values[$key]) || !is_numeric($this->values[$key])) {
            return $default;
        }

        return (int) $this->values[$key];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }
}
