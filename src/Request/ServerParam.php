<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Request;

/**
 * Typed accessor over a server-parameter snapshot (e.g. a captured copy of $_SERVER).
 *
 * The snapshot is passed in once at the edge of the application; the rest of the
 * codebase reads values from an instance and never touches the superglobal.
 */
final readonly class ServerParam
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(private array $snapshot)
    {
    }

    /**
     * @param array<mixed> $raw
     */
    public static function fromSuperglobal(array $raw): self
    {
        return new self(StringKeyed::fromArray($raw));
    }

    public function requestUri(): string
    {
        $value = $this->snapshot['REQUEST_URI'] ?? '/';

        return is_string($value) && $value !== '' ? $value : '/';
    }

    public function requestMethod(): string
    {
        $value = $this->snapshot['REQUEST_METHOD'] ?? 'GET';

        return is_string($value) && $value !== '' ? strtoupper($value) : 'GET';
    }

    public function publicSiteOrigin(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->snapshot['HTTP_HOST'] ?? $this->snapshot['SERVER_NAME'] ?? 'localhost';
        if (!is_string($host) || $host === '') {
            $host = 'localhost';
        }

        return $scheme . '://' . $host;
    }

    public function isSecure(): bool
    {
        $httpsRaw = $this->snapshot['HTTPS'] ?? '';
        $httpsStr = is_string($httpsRaw) ? strtolower($httpsRaw) : '';
        if ($httpsStr !== '' && $httpsStr !== 'off') {
            return true;
        }
        $portRaw = $this->snapshot['SERVER_PORT'] ?? null;
        if (is_int($portRaw)) {
            return $portRaw === 443;
        }
        if (is_string($portRaw) && ctype_digit($portRaw)) {
            return (int) $portRaw === 443;
        }

        return false;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->snapshot[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
