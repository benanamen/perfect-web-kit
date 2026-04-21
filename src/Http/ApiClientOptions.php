<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * Required configuration for {@see ApiClient}.
 *
 * Timeouts are required; there is no implicit "wait forever" path. Retries apply
 * only to idempotent GETs whose transport failed (curl error or status 0).
 */
final readonly class ApiClientOptions
{
    /**
     * @param non-empty-string $baseUrl
     * @param non-empty-string $idempotencyHeader
     * @param non-empty-string $userAgent
     */
    public function __construct(
        public string $baseUrl,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public TlsMode $tlsMode = TlsMode::Strict,
        public int $maxIdempotentRetries = 1,
        public string $idempotencyHeader = 'Idempotency-Key',
        public string $userAgent = 'PerfectWebKit/1.0',
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('ApiClient baseUrl must not be empty.');
        }
        if ($connectTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('connectTimeoutSeconds must be >= 1.');
        }
        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException('timeoutSeconds must be >= 1.');
        }
        if ($maxIdempotentRetries < 0) {
            throw new \InvalidArgumentException('maxIdempotentRetries must be >= 0.');
        }
        if ($idempotencyHeader === '') {
            throw new \InvalidArgumentException('idempotencyHeader must not be empty.');
        }
        if ($userAgent === '') {
            throw new \InvalidArgumentException('userAgent must not be empty.');
        }
    }
}
