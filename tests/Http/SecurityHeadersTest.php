<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Http;

use InvalidArgumentException;
use PerfectApp\WebKit\Http\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testProductionDefaultsEmitHardenedHeaders(): void
    {
        $headers = SecurityHeaders::productionDefaults()->toHeaders();

        self::assertArrayHasKey('Content-Security-Policy', $headers);
        self::assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
        self::assertStringContainsString("frame-ancestors 'none'", $headers['Content-Security-Policy']);
        self::assertSame('max-age=31536000; includeSubDomains; preload', $headers['Strict-Transport-Security']);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertArrayHasKey('Permissions-Policy', $headers);
    }

    public function testDevelopmentDefaultsDropHsts(): void
    {
        $headers = SecurityHeaders::developmentDefaults()->toHeaders();

        self::assertArrayNotHasKey('Strict-Transport-Security', $headers);
        self::assertArrayHasKey('Content-Security-Policy', $headers);
        self::assertSame('DENY', $headers['X-Frame-Options']);
    }

    public function testWithContentSecurityPolicyOverridesCsp(): void
    {
        $headers = SecurityHeaders::productionDefaults()
            ->withContentSecurityPolicy("default-src 'self' https://cdn.example")
            ->toHeaders();

        self::assertSame("default-src 'self' https://cdn.example", $headers['Content-Security-Policy']);
    }

    public function testWithContentSecurityPolicyNullOmitsHeader(): void
    {
        $headers = SecurityHeaders::productionDefaults()
            ->withContentSecurityPolicy(null)
            ->toHeaders();

        self::assertArrayNotHasKey('Content-Security-Policy', $headers);
    }

    public function testWithHstsBuildsValueAndValidatesMaxAge(): void
    {
        $headers = SecurityHeaders::productionDefaults()
            ->withHsts(60, includeSubDomains: false, preload: false)
            ->toHeaders();

        self::assertSame('max-age=60', $headers['Strict-Transport-Security']);

        $this->expectException(InvalidArgumentException::class);
        SecurityHeaders::productionDefaults()->withHsts(-1);
    }

    public function testWithFrameOptionsRejectsOtherValues(): void
    {
        $ok = SecurityHeaders::productionDefaults()->withFrameOptions('sameorigin')->toHeaders();
        self::assertSame('SAMEORIGIN', $ok['X-Frame-Options']);

        $this->expectException(InvalidArgumentException::class);
        SecurityHeaders::productionDefaults()->withFrameOptions('ALLOW-FROM https://evil');
    }

    public function testWithReferrerPolicyRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecurityHeaders::productionDefaults()->withReferrerPolicy('   ');
    }

    public function testWithPermissionsPolicyRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecurityHeaders::productionDefaults()->withPermissionsPolicy('   ');
    }

    public function testWithExtraHeaderAddsValueAndRejectsCrlf(): void
    {
        $headers = SecurityHeaders::productionDefaults()
            ->withExtraHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->toHeaders();

        self::assertSame('same-origin', $headers['Cross-Origin-Opener-Policy']);

        $this->expectException(InvalidArgumentException::class);
        SecurityHeaders::productionDefaults()->withExtraHeader('X-Bad', "evil\r\nSet-Cookie: boom=1");
    }

    public function testApplyRoutesAllHeadersThroughInjectedSender(): void
    {
        $captured = [];
        SecurityHeaders::productionDefaults()->apply(static function (string $line) use (&$captured): void {
            $captured[] = $line;
        });

        self::assertContains('X-Frame-Options: DENY', $captured);
        self::assertContains('X-Content-Type-Options: nosniff', $captured);
    }

    public function testWithFrameAncestorsReplacesDirectiveInline(): void
    {
        $policy = SecurityHeaders::productionDefaults()
            ->withFrameAncestors(['self', 'https://admin.example'])
            ->contentSecurityPolicy;

        self::assertIsString($policy);
        self::assertStringContainsString("frame-ancestors 'self' https://admin.example", $policy);
        self::assertStringNotContainsString("frame-ancestors 'none'", $policy);
    }

    public function testWithFrameAncestorsOnEmptyCspCreatesOnlyDirective(): void
    {
        $policy = SecurityHeaders::productionDefaults()
            ->withContentSecurityPolicy(null)
            ->withFrameAncestors(['none'])
            ->contentSecurityPolicy;

        self::assertSame("frame-ancestors 'none'", $policy);
    }
}
