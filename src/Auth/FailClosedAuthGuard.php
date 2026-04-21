<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Auth;

use Psr\Log\LoggerInterface;

/**
 * Generic "deny unless explicitly allowed" auth guard.
 *
 * The guard calls {@see IdentityProbeInterface::probe()} once and only allows
 * the request to continue when the result is {@see AuthProbeResult::Authenticated}.
 * Every other outcome clears the local session and triggers a redirect.
 *
 * This class replaces ad hoc AuthGuards that treat only HTTP 401 as
 * unauthenticated and silently continue on every other failure mode.
 */
final readonly class FailClosedAuthGuard
{
    public function __construct(
        private IdentityProbeInterface $probe,
        private SessionClearerInterface $sessionClearer,
        private LoginRedirectorInterface $redirector,
        private LoggerInterface $logger,
        private string $sessionExpiredMessage = 'Your session expired. Please sign in again.',
        private string $upstreamUnreachableMessage = 'We could not verify your session right now. Please sign in again.',
    ) {
    }

    /**
     * Deny unless explicitly authenticated. Returns normally on success,
     * never returns otherwise (redirect + exit inside the redirector).
     */
    public function requireAuth(): void
    {
        $result = $this->probe->probe();

        switch ($result) {
            case AuthProbeResult::Authenticated:
                return;
            case AuthProbeResult::ExplicitlyRejected:
                $this->logger->info('Auth guard: identity probe rejected session');
                $this->sessionClearer->clear();
                $this->redirector->redirect($this->sessionExpiredMessage);
            case AuthProbeResult::Unreachable:
                $this->logger->warning('Auth guard: identity probe unreachable');
                $this->sessionClearer->clear();
                $this->redirector->redirect($this->upstreamUnreachableMessage);
            case AuthProbeResult::Indeterminate:
                $this->logger->warning('Auth guard: identity probe returned indeterminate status');
                $this->sessionClearer->clear();
                $this->redirector->redirect($this->sessionExpiredMessage);
        }
    }
}
