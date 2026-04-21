<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Http;

use PerfectApp\WebKit\Http\ApiClientOptions;
use PerfectApp\WebKit\Http\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiClientOptions::class)]
final class ApiClientOptionsTest extends TestCase
{
    public function testAcceptsSensibleDefaults(): void
    {
        $options = new ApiClientOptions(
            baseUrl: 'https://api.example.com',
            connectTimeoutSeconds: 2,
            timeoutSeconds: 10,
        );
        $this->assertSame(TlsMode::Strict, $options->tlsMode);
        $this->assertSame(1, $options->maxIdempotentRetries);
        $this->assertSame('Idempotency-Key', $options->idempotencyHeader);
    }

    public function testRejectsEmptyBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiClientOptions('', 1, 1);
    }

    public function testRejectsNonPositiveTimeouts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiClientOptions('https://x', 0, 1);
    }

    public function testRejectsNegativeRetries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiClientOptions('https://x', 1, 1, maxIdempotentRetries: -1);
    }
}
