<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Auth;

/**
 * Emits the HTTP redirect that sends the browser back to the login page.
 *
 * The implementation decides what the target URL is, whether to attach a
 * flash message, and whether to call {@see exit()} — but must never return.
 */
interface LoginRedirectorInterface
{
    public function redirect(?string $flashMessage = null): never;
}
