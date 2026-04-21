<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Http;

use PerfectApp\WebKit\Http\Cookie\CookieJar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieJar::class)]
final class CookieJarTest extends TestCase
{
    public function testExtractsAllSetCookieLinesCaseInsensitively(): void
    {
        $block = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nset-cookie: a=1; Path=/\r\nSet-Cookie: b=2; Path=/\r\n";
        $values = CookieJar::extractSetCookieLines($block);
        $this->assertSame(['a=1; Path=/', 'b=2; Path=/'], $values);
    }

    public function testToCookieHeaderStripsAttributes(): void
    {
        $header = CookieJar::toCookieHeader(['a=1; Path=/', 'b=2; HttpOnly']);
        $this->assertSame('a=1; b=2', $header);
    }

    public function testReturnsNullWhenNoCookies(): void
    {
        $this->assertNull(CookieJar::toCookieHeader([]));
        $this->assertNull(CookieJar::toCookieHeader(['  ']));
    }
}
