<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * PSR-3 decorator that merges a per-request context (request_id, tenant_slug,
 * user_id, route, …) into every log record. Call-site context always wins
 * over the mutable base context so a specific log entry can override, e.g.,
 * the tenant_slug when operating cross-tenant.
 *
 * Intentionally mutable via {@see self::withContext()} so middleware can
 * enrich the context as the request progresses (tenant resolution, auth
 * resolution, …) without having to re-decorate the logger each time.
 */
final class ContextLogger extends AbstractLogger
{
    /** @var array<string, scalar|null> */
    private array $base = [];

    public function __construct(private readonly LoggerInterface $inner)
    {
    }

    /**
     * @param array<string, scalar|null> $context Values merged into every
     *                                            subsequent log record.
     */
    public function withContext(array $context): void
    {
        foreach ($context as $key => $value) {
            if ($key === '' || !is_string($key)) {
                continue;
            }
            if ($value === null) {
                unset($this->base[$key]);

                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $this->base[$key] = $value;
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    public function baseContext(): array
    {
        return $this->base;
    }

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        /** @var array<string, mixed> $merged */
        $merged = $this->base;
        foreach ($context as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $merged[$key] = $value;
        }
        $this->inner->log($level, $message, $merged);
    }
}
