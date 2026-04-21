<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Logging;

use PerfectApp\WebKit\Logging\DailyFileLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;

final class DailyFileLoggerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pwk-daily-' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->dir);
    }

    public function testWritesToDateStampedFile(): void
    {
        $logger = new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'app.log', 0);
        $logger->info('hello', ['k' => 'v']);

        $today = (new \DateTimeImmutable('now'))->format('Y-m-d');
        $expected = $this->dir . DIRECTORY_SEPARATOR . 'app-' . $today . '.log';
        $this->assertFileExists($expected);
        $contents = (string) file_get_contents($expected);
        $this->assertStringContainsString('INFO', $contents);
        $this->assertStringContainsString('hello', $contents);
        $this->assertStringContainsString('"k":"v"', $contents);
    }

    public function testStripsExistingDotLogSuffixFromBasePath(): void
    {
        $logger = new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'web.log', 0);
        $logger->warning('careful');

        $today = (new \DateTimeImmutable('now'))->format('Y-m-d');
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'web-' . $today . '.log');
    }

    public function testInvalidLevelThrows(): void
    {
        $logger = new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'app.log', 0);
        $this->expectException(InvalidArgumentException::class);
        $logger->log('not-a-level', 'boom');
    }

    public function testRejectsNegativeRetention(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'app.log', -1);
    }

    public function testPrunesOldFilesBeyondRetention(): void
    {
        $stale = $this->dir . DIRECTORY_SEPARATOR . 'app-2000-01-01.log';
        file_put_contents($stale, "old\n");
        touch($stale, time() - 86400 * 10);
        $fresh = $this->dir . DIRECTORY_SEPARATOR . 'app-2099-12-31.log';
        file_put_contents($fresh, "new\n");
        touch($fresh, time());

        $logger = new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'app.log', 3);
        $logger->info('trigger prune');

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh);
    }

    public function testRetentionZeroDisablesPruning(): void
    {
        $stale = $this->dir . DIRECTORY_SEPARATOR . 'app-2000-01-01.log';
        file_put_contents($stale, "old\n");
        touch($stale, time() - 86400 * 10);

        $logger = new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'app.log', 0);
        $logger->info('no prune');

        $this->assertFileExists($stale);
    }

    public function testCurrentPathReflectsToday(): void
    {
        $logger = new DailyFileLogger($this->dir . DIRECTORY_SEPARATOR . 'web.log', 0);
        $today = (new \DateTimeImmutable('now'))->format('Y-m-d');
        $this->assertSame(
            $this->dir . DIRECTORY_SEPARATOR . 'web-' . $today . '.log',
            $logger->currentPath()
        );
    }
}
