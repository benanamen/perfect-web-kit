# perfect-web-kit

Shared PHP 8.5 web-UI toolkit used by the Consigngold storefront (`curser-pos-web`)
and SaaS admin (`curser-pos-admin`). It centralizes cross-cutting infrastructure
that was previously duplicated and drifted between the two UI applications.

## Contents

| Component | Purpose |
|-----------|---------|
| `PerfectApp\WebKit\Http\ApiClient` | JSON HTTP client with required timeouts, typed responses, transport exceptions, and bounded retries on idempotent requests only. |
| `PerfectApp\WebKit\Http\ApiResponse` | Immutable response with classified status helpers. |
| `PerfectApp\WebKit\Http\ApiClientOptions` | Validated options: base URL, connect/total timeout, TLS mode, retry policy. |
| `PerfectApp\WebKit\Http\Cookie\CookieJar` | Parse `Set-Cookie` headers and rebuild a single `Cookie` request header. |
| `PerfectApp\WebKit\Request\StringKeyed` | Narrow mixed-key arrays to string-keyed maps. |
| `PerfectApp\WebKit\Request\ServerParam` | Typed accessor over a `$_SERVER` snapshot. |
| `PerfectApp\WebKit\Request\RequestValue` | Narrow mixed values to string/int/float/bool without unsafe casts. |
| `PerfectApp\WebKit\Session\SessionCookieConfig` | Secure, `__Host-`-capable cookie parameter setter for `session_*`. |
| `PerfectApp\WebKit\Auth\FailClosedAuthGuard` | "Deny unless explicitly authenticated" guard for UI requests. |
| `PerfectApp\WebKit\Bootstrap\PhpErrorHandling` | PSR-3 error/shutdown handler install, with dev/prod display toggle. |
| `PerfectApp\WebKit\Bootstrap\EnvFileLoader` | Dotenv parser that produces an immutable `EnvConfig`; does not mutate `$_ENV`. |
| `PerfectApp\WebKit\Logging\BootstrapLogger` | Tiny STDERR PSR-3 logger for pre-container bootstrap use. |
| `PerfectApp\WebKit\Util\UsDateDisplay` | mm-dd-YYYY / dd-mm-YYYY formatting with naive-midnight and timezone rules. |

## Assets

- `assets/sortable-tables.js` — PHP-natural-order client-side `.data-table` sort.
- `assets/date-from-to-sync.js` — Keeps `#from` / `#to` date-range inputs coherent on the client.

Storefront / admin ship these files from their asset pipelines; they should be served directly by the web server with long-lived cache headers.

## Bin

- `vendor/bin/check-git-path-case-collisions.php` — fail CI when tracked paths collide case-insensitively.
- `vendor/bin/apply-codeception-windows-regex-fix.php` — patch vendor Codeception so `codecept run functional` works on Windows / PHP 8.5.

## Testing

```bash
composer install
composer test
composer phpstan
```
