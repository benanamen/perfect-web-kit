<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Auth;

/**
 * Outcome of a single identity probe made by an {@see IdentityProbeInterface}.
 *
 * {@see self::Authenticated} is the only outcome that allows the request to proceed.
 * Every other value instructs {@see FailClosedAuthGuard} to terminate the request.
 */
enum AuthProbeResult: string
{
    /** Upstream confirmed the identity is valid. */
    case Authenticated = 'authenticated';

    /** Upstream explicitly rejected the session (e.g. HTTP 401). Flash "session expired". */
    case ExplicitlyRejected = 'explicitly-rejected';

    /** Transport failure: upstream unreachable, timed out, or returned 5xx/malformed JSON. */
    case Unreachable = 'unreachable';

    /** Any other non-authenticated response (403, 404, 400, ...). Treated as a hard failure. */
    case Indeterminate = 'indeterminate';
}
