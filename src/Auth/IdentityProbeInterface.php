<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Auth;

/**
 * Probes the upstream identity provider once per request to determine whether
 * the current session is still valid. Implementations translate upstream
 * responses to an {@see AuthProbeResult}; they do not raise on non-2xx.
 */
interface IdentityProbeInterface
{
    public function probe(): AuthProbeResult;
}
