<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * Immutable result of a single {@see ApiClient::request()} call.
 *
 * {@see $body} is always a decoded JSON structure: either an associative map
 * (object response) or a list (array response). Unparseable bodies raise
 * {@see ApiTransportException} on the client side; callers never see a "raw"
 * stringly-typed field.
 *
 * @phpstan-type JsonMap array<string, mixed>
 * @phpstan-type JsonList list<mixed>
 */
final readonly class ApiResponse
{
    /**
     * @param array<string, list<string>> $headers lower-cased header name => list of values
     * @param JsonMap|JsonList $body
     */
    public function __construct(
        public int $status,
        public array $headers,
        public array $body,
        public ?string $sessionCookieHeader,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }

    /**
     * @return JsonMap
     */
    public function bodyMap(): array
    {
        if (!array_is_list($this->body)) {
            /** @var JsonMap $body */
            $body = $this->body;

            return $body;
        }

        throw new \LogicException('Response body is a JSON list, not a map.');
    }

    /**
     * @return JsonList
     */
    public function bodyList(): array
    {
        if (array_is_list($this->body)) {
            /** @var JsonList $body */
            $body = $this->body;

            return $body;
        }

        throw new \LogicException('Response body is a JSON map, not a list.');
    }
}
