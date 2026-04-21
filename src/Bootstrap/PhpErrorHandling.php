<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Bootstrap;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Installs PHP error and shutdown handlers that log via PSR-3.
 *
 * PHP's own error log is turned off; all PHP-originated errors reach the
 * application logger. Display of errors on the response is controlled by the
 * caller via the $displayErrors flag (true in local/dev, false in production).
 */
final class PhpErrorHandling
{
    private static bool $registered = false;

    public static function register(LoggerInterface $logger, bool $displayErrors): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        ini_set('display_errors', $displayErrors ? '1' : '0');
        ini_set('display_startup_errors', $displayErrors ? '1' : '0');
        ini_set('log_errors', '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            $logger->log(self::mapSeverityToPsrLevel($severity), $message, [
                'php_severity' => $severity,
                'file' => $file,
                'line' => $line,
            ]);

            return false;
        });

        register_shutdown_function(static function () use ($logger): void {
            $last = error_get_last();
            if ($last === null) {
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($last['type'], $fatal, true)) {
                return;
            }
            $logger->critical($last['message'], [
                'php_type' => $last['type'],
                'file' => $last['file'],
                'line' => $last['line'],
            ]);
        });
    }

    /**
     * For tests only. Allows a fresh {@see self::register()} call.
     */
    public static function resetForTesting(): void
    {
        self::$registered = false;
    }

    private static function mapSeverityToPsrLevel(int $severity): string
    {
        return match ($severity) {
            E_USER_ERROR, E_ERROR, E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_USER_WARNING, E_WARNING => LogLevel::WARNING,
            E_USER_NOTICE, E_NOTICE => LogLevel::NOTICE,
            E_USER_DEPRECATED, E_DEPRECATED => LogLevel::DEBUG,
            default => LogLevel::WARNING,
        };
    }
}
