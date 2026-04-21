<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Logging;

use Psr\Log\AbstractLogger;

/**
 * Tiny PSR-3 logger used during application bootstrap, before the real logger
 * is available. Writes to STDERR; never falls back to silent drop.
 *
 * Production applications should replace this with their real logger as soon
 * as the container is wired. This class exists so that the bootstrap path
 * does not need to reach for {@see error_log()} in violation of the project's
 * PSR-3 policy.
 */
final class BootstrapLogger extends AbstractLogger
{
    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream Writable stream; defaults to STDERR.
     */
    public function __construct($stream = null)
    {
        if ($stream === null) {
            /** @var resource $default */
            $default = defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');
            $this->stream = $default;

            return;
        }
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('BootstrapLogger requires a writable resource.');
        }
        $this->stream = $stream;
    }

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, \Stringable|string $message, array $context = []): void
    {
        $levelStr = is_string($level) ? strtoupper($level) : 'LOG';
        $line = \sprintf('[%s] %s %s', (new \DateTimeImmutable())->format('c'), $levelStr, (string) $message);
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if (is_string($encoded)) {
                $line .= ' ' . $encoded;
            }
        }
        fwrite($this->stream, $line . PHP_EOL);
    }
}
