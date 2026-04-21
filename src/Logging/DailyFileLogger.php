<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * PSR-3 logger that appends to a date-suffixed log file:
 *
 *   /var/log/app/web-2026-04-21.log
 *
 * On construction the supplied `$basePath` is the stem — the extension
 * `.log` is added automatically, and the date component is inserted
 * between the stem and the extension. A base path of
 * `/var/log/app/web.log` is accepted; the existing `.log` suffix is
 * stripped before re-application so operators can reuse their previous
 * configuration without surprises.
 *
 * Retention pruning is lazy: the first log call of a given request deletes
 * files for the same stem that are older than {@see self::$retentionDays}.
 * Callers that do not want pruning pass `0`. Pruning errors are swallowed
 * (they must never stop a log write from succeeding) but are written to
 * the underlying file as a NOTICE so they remain discoverable.
 */
final class DailyFileLogger extends AbstractLogger
{
    private const VALID_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    private string $stem;
    private string $directory;
    private string $baseName;
    private bool $prunedThisRequest = false;

    public function __construct(
        string $basePath,
        private readonly int $retentionDays = 30,
    ) {
        if ($basePath === '') {
            throw new \InvalidArgumentException('DailyFileLogger: basePath must not be empty.');
        }
        if ($this->retentionDays < 0) {
            throw new \InvalidArgumentException('DailyFileLogger: retentionDays must be >= 0.');
        }
        $this->stem = preg_replace('/\.log$/i', '', $basePath) ?? $basePath;
        $dir = dirname($this->stem);
        $this->directory = $dir === '' ? '.' : $dir;
        $this->baseName = basename($this->stem);
        if ($this->baseName === '') {
            throw new \InvalidArgumentException('DailyFileLogger: basePath must include a file stem.');
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('DailyFileLogger: unable to create log directory: ' . $this->directory);
        }
    }

    public function currentPath(): string
    {
        return $this->pathFor(new \DateTimeImmutable('now'));
    }

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        if (!is_string($level) || !in_array($level, self::VALID_LEVELS, true)) {
            throw new InvalidArgumentException('DailyFileLogger: invalid log level.');
        }
        $now = new \DateTimeImmutable('now');
        $path = $this->pathFor($now);
        $line = $this->format($level, $message, $context, $now);
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        $this->pruneOnce($path, $now);
    }

    private function pathFor(\DateTimeImmutable $now): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->baseName . '-' . $now->format('Y-m-d') . '.log';
    }

    /**
     * @param array<mixed> $context
     */
    private function format(string $level, \Stringable|string $message, array $context, \DateTimeImmutable $now): string
    {
        $encoded = $context === [] ? '' : (json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '');

        return sprintf(
            "[%s] %s %s%s%s",
            $now->format('Y-m-d H:i:s.u'),
            strtoupper($level),
            (string) $message,
            $encoded === '' ? '' : ' ' . $encoded,
            PHP_EOL,
        );
    }

    private function pruneOnce(string $currentFile, \DateTimeImmutable $now): void
    {
        if ($this->prunedThisRequest) {
            return;
        }
        $this->prunedThisRequest = true;
        if ($this->retentionDays === 0) {
            return;
        }
        $pattern = $this->directory . DIRECTORY_SEPARATOR . $this->baseName . '-*.log';
        $matches = glob($pattern);
        if (!is_array($matches)) {
            return;
        }
        $cutoff = $now->sub(new \DateInterval('P' . $this->retentionDays . 'D'))->getTimestamp();
        foreach ($matches as $candidate) {
            if ($candidate === $currentFile) {
                continue;
            }
            $mtime = @filemtime($candidate);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            @unlink($candidate);
        }
    }
}
