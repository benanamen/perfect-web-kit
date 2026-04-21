<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * Raised when the HTTP call itself could not complete: connection refused,
 * timeout, DNS failure, TLS handshake failure, unparseable response body,
 * or any condition where the remote never returned a well-formed response.
 */
final class ApiTransportException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $method,
        public readonly string $url,
        public readonly ?string $curlError = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
