<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Bootstrap;

use PerfectApp\WebKit\Bootstrap\EnvConfig;
use PerfectApp\WebKit\Bootstrap\EnvFileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvFileLoader::class)]
#[CoversClass(EnvConfig::class)]
final class EnvFileLoaderTest extends TestCase
{
    public function testParsesUnquotedQuotedAndBooleans(): void
    {
        $loader = new EnvFileLoader();
        $raw = <<<'ENV'
# header
DB_HOST=localhost
DB_NAME="my app"
SINGLE='raw $value'
APP_DEBUG=true
PORT=5432
export AWS_REGION=us-east-1

# comment only
INVALID LINE NO EQUALS
ENV;
        $config = $loader->loadFromString($raw);
        $this->assertSame('localhost', $config->string('DB_HOST'));
        $this->assertSame('my app', $config->string('DB_NAME'));
        $this->assertSame('raw $value', $config->string('SINGLE'));
        $this->assertTrue($config->bool('APP_DEBUG'));
        $this->assertSame(5432, $config->int('PORT'));
        $this->assertSame('us-east-1', $config->string('AWS_REGION'));
        $this->assertFalse($config->has('INVALID'));
    }

    public function testHonorsEscapesInDoubleQuotedValues(): void
    {
        $loader = new EnvFileLoader();
        $config = $loader->loadFromString('GREETING="hello\nworld"');
        $this->assertSame("hello\nworld", $config->string('GREETING'));
    }

    public function testRequiredThrowsWhenMissing(): void
    {
        $config = new EnvConfig([]);
        $this->expectException(\RuntimeException::class);
        $config->required('MISSING');
    }

    public function testNonExistentFileYieldsEmptyConfig(): void
    {
        $loader = new EnvFileLoader();
        $config = $loader->loadFromFile(__DIR__ . '/does-not-exist.env');
        $this->assertSame([], $config->all());
    }
}
