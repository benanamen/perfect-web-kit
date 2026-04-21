<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Request;

use PerfectApp\WebKit\Request\ServerParam;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerParam::class)]
final class ServerParamTest extends TestCase
{
    public function testReadsMethodAndUri(): void
    {
        $params = new ServerParam(['REQUEST_URI' => '/foo', 'REQUEST_METHOD' => 'post']);
        $this->assertSame('/foo', $params->requestUri());
        $this->assertSame('POST', $params->requestMethod());
    }

    public function testDefaultsForMissingValues(): void
    {
        $params = new ServerParam([]);
        $this->assertSame('/', $params->requestUri());
        $this->assertSame('GET', $params->requestMethod());
    }

    public function testPublicOriginPicksHttpsForPort443(): void
    {
        $params = new ServerParam(['HTTP_HOST' => 'example.com', 'SERVER_PORT' => 443]);
        $this->assertSame('https://example.com', $params->publicSiteOrigin());
    }

    public function testPublicOriginFallsBackToLocalhost(): void
    {
        $params = new ServerParam([]);
        $this->assertSame('http://localhost', $params->publicSiteOrigin());
    }
}
