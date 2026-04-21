<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Auth;

/**
 * Called by {@see FailClosedAuthGuard} whenever the current session must be
 * invalidated before redirecting the caller back to the login page.
 */
interface SessionClearerInterface
{
    public function clear(): void;
}
