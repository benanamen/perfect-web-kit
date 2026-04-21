<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Auth;

use PerfectApp\WebKit\Auth\AuthProbeResult;
use PerfectApp\WebKit\Auth\FailClosedAuthGuard;
use PerfectApp\WebKit\Auth\IdentityProbeInterface;
use PerfectApp\WebKit\Auth\LoginRedirectorInterface;
use PerfectApp\WebKit\Auth\SessionClearerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(FailClosedAuthGuard::class)]
final class FailClosedAuthGuardTest extends TestCase
{
    public function testAuthenticatedAllowsRequestToContinue(): void
    {
        $probe = new StubProbe(AuthProbeResult::Authenticated);
        $clearer = new StubClearer();
        $redirector = new StubRedirector();
        $guard = new FailClosedAuthGuard($probe, $clearer, $redirector, new NullLogger());
        $guard->requireAuth();
        $this->assertFalse($clearer->cleared);
        $this->assertFalse($redirector->redirected);
    }

    public function testExplicitlyRejectedClearsSessionAndRedirects(): void
    {
        $this->assertRedirect(AuthProbeResult::ExplicitlyRejected);
    }

    public function testUnreachableClearsSessionAndRedirects(): void
    {
        $this->assertRedirect(AuthProbeResult::Unreachable);
    }

    public function testIndeterminateClearsSessionAndRedirects(): void
    {
        $this->assertRedirect(AuthProbeResult::Indeterminate);
    }

    private function assertRedirect(AuthProbeResult $outcome): void
    {
        $probe = new StubProbe($outcome);
        $clearer = new StubClearer();
        $redirector = new StubRedirector();
        $guard = new FailClosedAuthGuard($probe, $clearer, $redirector, new NullLogger());
        try {
            $guard->requireAuth();
            $this->fail('Expected redirector to halt request.');
        } catch (\RuntimeException $expected) {
            $this->assertSame('redirected', $expected->getMessage());
        }
        $this->assertTrue($clearer->cleared);
        $this->assertTrue($redirector->redirected);
    }
}

final class StubProbe implements IdentityProbeInterface
{
    public function __construct(private readonly AuthProbeResult $outcome)
    {
    }

    public function probe(): AuthProbeResult
    {
        return $this->outcome;
    }
}

final class StubClearer implements SessionClearerInterface
{
    public bool $cleared = false;

    public function clear(): void
    {
        $this->cleared = true;
    }
}

final class StubRedirector implements LoginRedirectorInterface
{
    public bool $redirected = false;

    public function redirect(?string $flashMessage = null): never
    {
        $this->redirected = true;

        throw new \RuntimeException('redirected');
    }
}
