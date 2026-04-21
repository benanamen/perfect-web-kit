<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * How strictly the HTTP client should verify TLS on outbound requests.
 *
 * {@see self::Strict} is the only value acceptable in production. Other values exist
 * to make local development explicit and loud rather than relying on environment
 * variable fallbacks that are easy to leave enabled in production.
 */
enum TlsMode: string
{
    case Strict = 'strict';
    case AllowLocalDev = 'allow-local-dev';
    case DisabledForTesting = 'disabled-for-testing';
}
