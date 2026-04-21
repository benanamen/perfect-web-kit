<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Logging;

use PerfectApp\WebKit\Logging\ContextLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class ContextLoggerTest extends TestCase
{
    public function testMergesBaseContextIntoEveryRecord(): void
    {
        $capture = $this->capturingLogger();
        $logger = new ContextLogger($capture);
        $logger->withContext(['request_id' => 'abc', 'tenant_slug' => 't1']);
        $logger->info('user.login', ['user_id' => 42]);

        $records = $capture->records;
        $this->assertCount(1, $records);
        $this->assertSame('info', $records[0]['level']);
        $this->assertSame('user.login', $records[0]['message']);
        $this->assertSame([
            'request_id' => 'abc',
            'tenant_slug' => 't1',
            'user_id' => 42,
        ], $records[0]['context']);
    }

    public function testCallSiteContextWinsOnKeyCollision(): void
    {
        $capture = $this->capturingLogger();
        $logger = new ContextLogger($capture);
        $logger->withContext(['tenant_slug' => 'default']);
        $logger->warning('tenant.switch', ['tenant_slug' => 'override']);

        $this->assertSame('override', $capture->records[0]['context']['tenant_slug']);
    }

    public function testNullValueRemovesBaseEntry(): void
    {
        $capture = $this->capturingLogger();
        $logger = new ContextLogger($capture);
        $logger->withContext(['user_id' => 7]);
        $logger->withContext(['user_id' => null]);
        $logger->info('user.logout');

        $this->assertSame([], $capture->records[0]['context']);
    }

    public function testNonScalarBaseEntriesAreIgnored(): void
    {
        $capture = $this->capturingLogger();
        $logger = new ContextLogger($capture);
        $logger->withContext([
            'keep' => 'yes',
            'drop' => ['array', 'value'],
        ]);

        $this->assertSame(['keep' => 'yes'], $logger->baseContext());
    }

    private function capturingLogger(): LoggerInterface
    {
        return new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            /**
             * @param array<mixed> $context
             */
            public function log(mixed $level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
