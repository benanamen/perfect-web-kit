<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Http;

/**
 * Value object that composes a production-grade set of HTTP security
 * response headers. Instances are immutable; all `with*` methods return a
 * new object with the requested change applied.
 *
 * Typical usage:
 *
 * ```
 * SecurityHeaders::productionDefaults()
 *     ->withContentSecurityPolicy("default-src 'self'; img-src 'self' data:;")
 *     ->withFrameAncestors(['none'])
 *     ->apply();
 * ```
 *
 * The class does not read superglobals. It does not conditionally decide
 * whether the connection is HTTPS: that's the caller's job (typically a
 * bootstrap that already knows the configured TLS mode). `apply()` calls
 * `header()` by default, or any injected sender for tests.
 */
final readonly class SecurityHeaders
{
    public const DEFAULT_HSTS_MAX_AGE = 31_536_000;
    public const DEFAULT_REFERRER_POLICY = 'strict-origin-when-cross-origin';
    public const DEFAULT_PERMISSIONS_POLICY = 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()';

    /**
     * @param ?string $contentSecurityPolicy Set to null to omit the CSP header entirely.
     * @param ?string $hstsValue             Set to null to omit HSTS (e.g. on plain HTTP in dev).
     * @param array<string, string> $extraHeaders Map of header name to value that is appended verbatim.
     */
    public function __construct(
        public ?string $contentSecurityPolicy,
        public ?string $hstsValue,
        public string $frameOptions,
        public string $referrerPolicy,
        public string $permissionsPolicy,
        public bool $contentTypeNoSniff,
        public array $extraHeaders,
    ) {
    }

    /**
     * Hardened defaults suitable for a production HTTPS deployment. The CSP
     * is intentionally strict (default-src 'self'); each app layers on only
     * what it actually needs.
     */
    public static function productionDefaults(): self
    {
        return new self(
            contentSecurityPolicy: "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; upgrade-insecure-requests",
            hstsValue: self::buildHstsValue(self::DEFAULT_HSTS_MAX_AGE, true, true),
            frameOptions: 'DENY',
            referrerPolicy: self::DEFAULT_REFERRER_POLICY,
            permissionsPolicy: self::DEFAULT_PERMISSIONS_POLICY,
            contentTypeNoSniff: true,
            extraHeaders: [],
        );
    }

    /**
     * Same policy as production but with HSTS disabled. Use this when
     * running on plain HTTP in local dev so browsers do not pin the domain
     * to HTTPS and break your next `http://` request.
     */
    public static function developmentDefaults(): self
    {
        return self::productionDefaults()->withHstsDisabled();
    }

    public function withContentSecurityPolicy(?string $policy): self
    {
        return new self(
            contentSecurityPolicy: $policy,
            hstsValue: $this->hstsValue,
            frameOptions: $this->frameOptions,
            referrerPolicy: $this->referrerPolicy,
            permissionsPolicy: $this->permissionsPolicy,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $this->extraHeaders,
        );
    }

    public function withHsts(int $maxAgeSeconds, bool $includeSubDomains = true, bool $preload = false): self
    {
        if ($maxAgeSeconds < 0) {
            throw new \InvalidArgumentException('HSTS max-age must be non-negative.');
        }

        return new self(
            contentSecurityPolicy: $this->contentSecurityPolicy,
            hstsValue: self::buildHstsValue($maxAgeSeconds, $includeSubDomains, $preload),
            frameOptions: $this->frameOptions,
            referrerPolicy: $this->referrerPolicy,
            permissionsPolicy: $this->permissionsPolicy,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $this->extraHeaders,
        );
    }

    public function withHstsDisabled(): self
    {
        return new self(
            contentSecurityPolicy: $this->contentSecurityPolicy,
            hstsValue: null,
            frameOptions: $this->frameOptions,
            referrerPolicy: $this->referrerPolicy,
            permissionsPolicy: $this->permissionsPolicy,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $this->extraHeaders,
        );
    }

    public function withFrameOptions(string $value): self
    {
        $normalized = strtoupper(trim($value));
        if (!in_array($normalized, ['DENY', 'SAMEORIGIN'], true)) {
            throw new \InvalidArgumentException('X-Frame-Options must be DENY or SAMEORIGIN.');
        }

        return new self(
            contentSecurityPolicy: $this->contentSecurityPolicy,
            hstsValue: $this->hstsValue,
            frameOptions: $normalized,
            referrerPolicy: $this->referrerPolicy,
            permissionsPolicy: $this->permissionsPolicy,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $this->extraHeaders,
        );
    }

    public function withReferrerPolicy(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Referrer-Policy must not be empty.');
        }

        return new self(
            contentSecurityPolicy: $this->contentSecurityPolicy,
            hstsValue: $this->hstsValue,
            frameOptions: $this->frameOptions,
            referrerPolicy: $trimmed,
            permissionsPolicy: $this->permissionsPolicy,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $this->extraHeaders,
        );
    }

    public function withPermissionsPolicy(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Permissions-Policy must not be empty.');
        }

        return new self(
            contentSecurityPolicy: $this->contentSecurityPolicy,
            hstsValue: $this->hstsValue,
            frameOptions: $this->frameOptions,
            referrerPolicy: $this->referrerPolicy,
            permissionsPolicy: $trimmed,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $this->extraHeaders,
        );
    }

    public function withExtraHeader(string $name, string $value): self
    {
        $trimmedName = trim($name);
        if ($trimmedName === '' || preg_match('/[\r\n]/', $trimmedName) === 1) {
            throw new \InvalidArgumentException('Header name must be non-empty and must not contain CR/LF.');
        }
        if (preg_match('/[\r\n]/', $value) === 1) {
            throw new \InvalidArgumentException('Header value must not contain CR/LF.');
        }
        $extra = $this->extraHeaders;
        $extra[$trimmedName] = $value;

        return new self(
            contentSecurityPolicy: $this->contentSecurityPolicy,
            hstsValue: $this->hstsValue,
            frameOptions: $this->frameOptions,
            referrerPolicy: $this->referrerPolicy,
            permissionsPolicy: $this->permissionsPolicy,
            contentTypeNoSniff: $this->contentTypeNoSniff,
            extraHeaders: $extra,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        $out = [];
        if ($this->contentSecurityPolicy !== null && $this->contentSecurityPolicy !== '') {
            $out['Content-Security-Policy'] = $this->contentSecurityPolicy;
        }
        if ($this->hstsValue !== null && $this->hstsValue !== '') {
            $out['Strict-Transport-Security'] = $this->hstsValue;
        }
        $out['X-Frame-Options'] = $this->frameOptions;
        $out['Referrer-Policy'] = $this->referrerPolicy;
        $out['Permissions-Policy'] = $this->permissionsPolicy;
        if ($this->contentTypeNoSniff) {
            $out['X-Content-Type-Options'] = 'nosniff';
        }
        foreach ($this->extraHeaders as $name => $value) {
            $out[$name] = $value;
        }

        return $out;
    }

    /**
     * Push the computed headers. `$sender` exists so tests can capture the
     * calls without the SUT reaching for `header()`. In production, callers
     * almost always pass null and get the default `header()` behaviour.
     *
     * @param ?callable(string): void $sender
     */
    public function apply(?callable $sender = null): void
    {
        $push = $sender ?? static function (string $line): void {
            header($line);
        };
        foreach ($this->toHeaders() as $name => $value) {
            $push($name . ': ' . $value);
        }
    }

    private static function buildHstsValue(int $maxAgeSeconds, bool $includeSubDomains, bool $preload): string
    {
        $parts = ['max-age=' . $maxAgeSeconds];
        if ($includeSubDomains) {
            $parts[] = 'includeSubDomains';
        }
        if ($preload) {
            $parts[] = 'preload';
        }

        return implode('; ', $parts);
    }

    /**
     * Convenience for Content-Security-Policy frame-ancestors rebuilds. Keeps
     * the rest of an existing policy intact and replaces only the directive.
     *
     * @param list<string> $sources
     */
    public function withFrameAncestors(array $sources): self
    {
        if ($sources === []) {
            throw new \InvalidArgumentException('frame-ancestors requires at least one source.');
        }
        $csp = $this->contentSecurityPolicy;
        $directive = 'frame-ancestors ' . self::quoteIfKeyword($sources);
        if ($csp === null || $csp === '') {
            return $this->withContentSecurityPolicy($directive);
        }
        $replaced = preg_replace('/\bframe-ancestors\b[^;]*/', $directive, $csp);
        if (!is_string($replaced) || $replaced === $csp) {
            $replaced = rtrim($csp, '; ') . '; ' . $directive;
        }

        return $this->withContentSecurityPolicy($replaced);
    }

    /**
     * @param list<string> $sources
     */
    private static function quoteIfKeyword(array $sources): string
    {
        $out = [];
        foreach ($sources as $source) {
            $trimmed = trim($source);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^(self|none|unsafe-inline|unsafe-eval)$/', $trimmed) === 1) {
                $out[] = "'" . $trimmed . "'";
                continue;
            }
            $out[] = $trimmed;
        }
        if ($out === []) {
            throw new \InvalidArgumentException('No usable frame-ancestors sources supplied.');
        }

        return implode(' ', $out);
    }
}
