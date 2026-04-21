<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

use PerfectApp\WebKit\Http\Cookie\CookieJar;
use Psr\Log\LoggerInterface;

/**
 * Typed JSON HTTP client used by UI layers to call an upstream API.
 *
 * Behavior:
 *  - Every request has a mandatory connect timeout and total timeout ({@see ApiClientOptions}).
 *  - Non-2xx statuses are returned as {@see ApiResponse}; callers decide how to react.
 *  - Transport failures (cURL error, timeout, unparseable body) raise {@see ApiTransportException}.
 *  - GET and HEAD retry once on transport failure by default ({@see ApiClientOptions::$maxIdempotentRetries});
 *    POST/PUT/PATCH/DELETE never auto-retry — callers pass an {@see ApiClientOptions::$idempotencyHeader}
 *    value themselves if they need at-most-once semantics on unsafe methods.
 *  - Upstream Set-Cookie headers are surfaced as a ready-to-store Cookie string; cookie attributes are
 *    deliberately discarded since the cookie is re-sent to the same origin inside one browser session.
 *
 * Not in scope:
 *  - BOM stripping. The upstream API must emit clean JSON; a BOM is a bug at the source and
 *    raises {@see ApiTransportException}.
 *  - `$_ENV` / `getenv` reads. All configuration comes from {@see ApiClientOptions}.
 */
final class ApiClient
{
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly ApiClientOptions $options,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Build an absolute API URL for logging or user-facing rendering.
     */
    /**
     * @return non-empty-string
     */
    public function url(string $path): string
    {
        $base = rtrim($this->options->baseUrl, '/');
        $suffix = str_starts_with($path, '/') ? $path : '/' . $path;

        return $base . $suffix;
    }

    /**
     * Perform an HTTP request against the configured API.
     *
     * @param non-empty-string $method Uppercase HTTP method (GET, POST, ...)
     * @param string $path Path starting with "/" or absolute URL under the configured base
     * @param ?string $upstreamCookie Cookie header to forward (e.g. API session cookie from local session)
     * @param ?string $jsonBody Raw JSON body to send; must already be a valid JSON document
     * @param array<string, string> $headers Additional request headers; Content-Type defaults to application/json
     *
     * @throws ApiTransportException when the request cannot complete or its body is not valid JSON
     */
    public function request(
        string $method,
        string $path,
        ?string $upstreamCookie = null,
        ?string $jsonBody = null,
        array $headers = [],
    ): ApiResponse {
        if ($method === '') {
            throw new \InvalidArgumentException('ApiClient::request method must not be empty.');
        }
        $upper = strtoupper($method);
        $url = $this->url($path);
        $idempotent = in_array($upper, self::IDEMPOTENT_METHODS, true);
        $maxAttempts = 1 + ($idempotent ? $this->options->maxIdempotentRetries : 0);

        $lastError = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->singleRequest($upper, $url, $upstreamCookie, $jsonBody, $headers);
            } catch (ApiTransportException $e) {
                $lastError = $e;
                if (!$idempotent || $attempt === $maxAttempts) {
                    $this->logger->warning('API request failed', [
                        'method' => $upper,
                        'url' => $url,
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'curl_error' => $e->curlError,
                    ]);

                    throw $e;
                }
                $this->logger->info('API request transport failure, retrying', [
                    'method' => $upper,
                    'url' => $url,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'curl_error' => $e->curlError,
                ]);
            }
        }

        throw $lastError ?? new ApiTransportException(
            'ApiClient exhausted retries without a definitive error.',
            $upper,
            $url,
        );
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $url
     * @param array<string, string> $headers
     *
     * @throws ApiTransportException
     */
    private function singleRequest(
        string $method,
        string $url,
        ?string $upstreamCookie,
        ?string $jsonBody,
        array $headers,
    ): ApiResponse {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new ApiTransportException('curl_init failed.', $method, $url);
        }

        try {
            $this->applyBaseOptions($handle, $method);
            $this->applyTlsOptions($handle, $url);
            $this->applyHeaders($handle, $headers, $upstreamCookie);
            $this->applyBody($handle, $method, $jsonBody);

            $rawResponse = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $curlError = curl_error($handle);
            $curlErrno = curl_errno($handle);

            if (!is_string($rawResponse) || $curlErrno !== 0 || $status === 0) {
                throw new ApiTransportException(
                    \sprintf('cURL error %d: %s', $curlErrno, $curlError !== '' ? $curlError : 'transport failure'),
                    $method,
                    $url,
                    $curlError !== '' ? $curlError : null,
                );
            }

            return $this->parseResponse($rawResponse, $headerSize, $status, $method, $url);
        } finally {
            curl_close($handle);
        }
    }

    /**
     * @param non-empty-string $method
     */
    private function applyBaseOptions(\CurlHandle $handle, string $method): void
    {
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->options->connectTimeoutSeconds);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->options->timeoutSeconds);
        curl_setopt($handle, CURLOPT_USERAGENT, $this->options->userAgent);
        curl_setopt($handle, CURLOPT_ACCEPT_ENCODING, '');
    }

    private function applyTlsOptions(\CurlHandle $handle, string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || strtolower($scheme) !== 'https') {
            return;
        }

        switch ($this->options->tlsMode) {
            case TlsMode::Strict:
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

                return;
            case TlsMode::AllowLocalDev:
                $host = parse_url($url, PHP_URL_HOST);
                if (is_string($host) && self::isLocalDevHost($host)) {
                    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

                    return;
                }
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

                return;
            case TlsMode::DisabledForTesting:
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);

                return;
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function applyHeaders(\CurlHandle $handle, array $headers, ?string $upstreamCookie): void
    {
        $final = $headers;
        $final['Accept'] ??= 'application/json';
        $final['Content-Type'] ??= 'application/json';
        if ($upstreamCookie !== null && $upstreamCookie !== '') {
            $final['Cookie'] = $upstreamCookie;
        }

        $lines = [];
        foreach ($final as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $lines);
    }

    private function applyBody(\CurlHandle $handle, string $method, ?string $jsonBody): void
    {
        if ($method === 'GET' || $method === 'HEAD') {
            return;
        }
        if ($jsonBody !== null && $jsonBody !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $jsonBody);

            return;
        }
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, '{}');
        }
    }

    /**
     * @throws ApiTransportException
     */
    private function parseResponse(
        string $rawResponse,
        int $headerSize,
        int $status,
        string $method,
        string $url,
    ): ApiResponse {
        if ($headerSize > 0 && strlen($rawResponse) >= $headerSize) {
            $headerBlock = substr($rawResponse, 0, $headerSize);
            $body = substr($rawResponse, $headerSize);
        } elseif (str_contains($rawResponse, "\r\n\r\n")) {
            [$headerBlock, $body] = explode("\r\n\r\n", $rawResponse, 2);
        } else {
            $headerBlock = '';
            $body = $rawResponse;
        }

        $decodedBody = $this->decodeJsonBody($body, $method, $url);
        $headers = self::parseHeaderBlock($headerBlock);
        $setCookies = CookieJar::extractSetCookieLines($headerBlock);
        $cookieHeader = CookieJar::toCookieHeader($setCookies);

        return new ApiResponse($status, $headers, $decodedBody, $cookieHeader);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     *
     * @throws ApiTransportException
     */
    private function decodeJsonBody(string $body, string $method, string $url): array
    {
        if ($body === '') {
            return [];
        }
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            throw new ApiTransportException(
                'Upstream response body starts with a UTF-8 BOM; API must emit clean JSON.',
                $method,
                $url,
            );
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiTransportException(
                'Upstream response body is not valid JSON: ' . $e->getMessage(),
                $method,
                $url,
                previous: $e,
            );
        }
        if (!is_array($decoded)) {
            throw new ApiTransportException(
                'Upstream response body is not a JSON array or object.',
                $method,
                $url,
            );
        }

        /** @var array<string, mixed>|list<mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, list<string>> lower-case header name => list of values
     */
    private static function parseHeaderBlock(string $block): array
    {
        if ($block === '') {
            return [];
        }
        $split = preg_split("/\r\n|\n|\r/", $block);
        $lines = $split === false ? [] : $split;
        $out = [];
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));
            if ($name === '' || $value === '') {
                continue;
            }
            $out[$name] ??= [];
            $out[$name][] = $value;
        }

        return $out;
    }

    private static function isLocalDevHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || $host === '::1') {
            return true;
        }

        return str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }
}
