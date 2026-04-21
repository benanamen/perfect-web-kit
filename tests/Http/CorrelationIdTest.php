<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Http;

use PerfectApp\WebKit\Http\CorrelationId;
use PHPUnit\Framework\TestCase;

final class CorrelationIdTest extends TestCase
{
    protected function setUp(): void
    {
        CorrelationId::reset();
    }

    public function testAcquireGeneratesUuidV4WhenNoHeaderPresent(): void
    {
        $id = CorrelationId::acquire([]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
        $this->assertSame($id, CorrelationId::current());
    }

    public function testAcquireAdoptsUpstreamRequestIdHeader(): void
    {
        $id = CorrelationId::acquire(['HTTP_X_REQUEST_ID' => 'abc-123_456']);
        $this->assertSame('abc-123_456', $id);
        $this->assertSame('abc-123_456', CorrelationId::current());
    }

    public function testAcquireAdoptsCorrelationHeaderWhenRequestIdMissing(): void
    {
        $id = CorrelationId::acquire(['HTTP_X_CORRELATION_ID' => 'tok.42']);
        $this->assertSame('tok.42', $id);
    }

    public function testRequestIdTakesPrecedenceOverCorrelationId(): void
    {
        $id = CorrelationId::acquire([
            'HTTP_X_REQUEST_ID' => 'req-win',
            'HTTP_X_CORRELATION_ID' => 'cor-lose',
        ]);
        $this->assertSame('req-win', $id);
    }

    public function testAcquireRejectsInjectionAttempts(): void
    {
        $id = CorrelationId::acquire(['HTTP_X_REQUEST_ID' => "abc\r\nSet-Cookie: pwn=1"]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function testAcquireRejectsOverlyLongValues(): void
    {
        $id = CorrelationId::acquire(['HTTP_X_REQUEST_ID' => str_repeat('a', 200)]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function testAcquireRejectsBlankUpstream(): void
    {
        $id = CorrelationId::acquire(['HTTP_X_REQUEST_ID' => "   \t  "]);
        $this->assertNotSame('', $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function testCurrentIsNullBeforeAcquire(): void
    {
        $this->assertNull(CorrelationId::current());
    }
}
