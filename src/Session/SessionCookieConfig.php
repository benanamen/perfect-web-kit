<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Session;

/**
 * Produce safe session cookie parameters. Applies {@see session_set_cookie_params()}
 * and {@see session_name()} without touching superglobals.
 *
 * When {@see $secure} is true and {@see $cookieName} has the "__Host-" or "__Secure-"
 * prefix, browsers enforce the stronger cookie rules automatically. The
 * {@see self::hostPrefix()} helper returns a safe default for HTTPS origins.
 */
final readonly class SessionCookieConfig
{
    /**
     * @param non-empty-string $cookieName
     * @param non-empty-string $path
     */
    public function __construct(
        public string $cookieName,
        public bool $secure,
        public SameSite $sameSite = SameSite::Lax,
        public string $path = '/',
        public int $lifetimeSeconds = 0,
    ) {
    }

    public function apply(): void
    {
        session_name($this->cookieName);
        session_set_cookie_params([
            'lifetime' => $this->lifetimeSeconds,
            'path' => $this->path,
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => $this->sameSite->value,
        ]);
    }

    /**
     * @return non-empty-string
     */
    public static function hostPrefix(string $baseName): string
    {
        if ($baseName === '') {
            throw new \InvalidArgumentException('hostPrefix baseName must not be empty.');
        }

        return '__Host-' . $baseName;
    }

    /**
     * Pick the correct cookie name + secure flag for a given request context.
     *
     * When {@see $secure} is true, the cookie gets the browser-enforced
     * "__Host-" prefix which mandates Secure, Path=/, and no Domain attribute.
     * When false (local HTTP dev), the bare $baseName is used so the session
     * still works over plaintext; it is never deployed that way in production.
     *
     * @param non-empty-string $baseName
     */
    public static function forHostProfile(
        string $baseName,
        bool $secure,
        SameSite $sameSite = SameSite::Lax,
    ): self {
        if ($baseName === '') {
            throw new \InvalidArgumentException('forHostProfile baseName must not be empty.');
        }

        $cookieName = $secure ? self::hostPrefix($baseName) : $baseName;

        return new self(
            cookieName: $cookieName,
            secure: $secure,
            sameSite: $sameSite,
        );
    }
}
