<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Session;

use InvalidArgumentException;
use PerfectApp\WebKit\Session\SameSite;
use PerfectApp\WebKit\Session\SessionCookieConfig;
use PHPUnit\Framework\TestCase;

final class SessionCookieConfigTest extends TestCase
{
    public function testHostPrefixProducesHostCookieName(): void
    {
        self::assertSame('__Host-curser_pos_admin', SessionCookieConfig::hostPrefix('curser_pos_admin'));
    }

    public function testHostPrefixRejectsEmptyBaseName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SessionCookieConfig::hostPrefix('');
    }

    public function testDefaultsAreSafe(): void
    {
        $config = new SessionCookieConfig(
            cookieName: '__Host-example',
            secure: true,
        );

        self::assertSame('__Host-example', $config->cookieName);
        self::assertTrue($config->secure);
        self::assertSame(SameSite::Lax, $config->sameSite);
        self::assertSame('/', $config->path);
        self::assertSame(0, $config->lifetimeSeconds);
    }

    public function testApplyIsCallableInCliContext(): void
    {
        $config = new SessionCookieConfig(
            cookieName: 'example',
            secure: false,
            sameSite: SameSite::Strict,
        );

        $config->apply();
        self::assertSame('example', session_name());
    }

    public function testForHostProfileProductionUsesHostPrefixAndSecure(): void
    {
        $config = SessionCookieConfig::forHostProfile('curser_pos_admin_session', true);

        self::assertSame('__Host-curser_pos_admin_session', $config->cookieName);
        self::assertTrue($config->secure);
        self::assertSame(SameSite::Lax, $config->sameSite);
        self::assertSame('/', $config->path);
    }

    public function testForHostProfileDevUsesPlainNameAndNonSecure(): void
    {
        $config = SessionCookieConfig::forHostProfile('curser_pos_admin_session', false);

        self::assertSame('curser_pos_admin_session', $config->cookieName);
        self::assertFalse($config->secure);
    }

    public function testForHostProfileRejectsEmptyBaseName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SessionCookieConfig::forHostProfile('', true);
    }
}
