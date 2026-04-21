<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * Raised by callers that prefer an exception to a value check. The client itself
 * returns {@see ApiResponse} regardless of HTTP status so that legitimate non-2xx
 * outcomes (401 unauthorized, 404 not found, etc.) can be handled in-flow.
 */
final class ApiStatusException extends \RuntimeException
{
    public function __construct(
        public readonly ApiResponse $response,
        public readonly string $method,
        public readonly string $url,
    ) {
        parent::__construct(
            \sprintf('API %s %s returned HTTP %d.', $method, $url, $response->status),
        );
    }
}
