<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Request;

use PerfectApp\WebKit\Request\RequestValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestValue::class)]
final class RequestValueTest extends TestCase
{
    public function testStringCoercion(): void
    {
        $this->assertSame('hello', RequestValue::string('hello'));
        $this->assertSame('42', RequestValue::string(42));
        $this->assertSame('1', RequestValue::string(true));
        $this->assertSame('default', RequestValue::string(null, 'default'));
    }

    public function testIntCoercion(): void
    {
        $this->assertSame(5, RequestValue::int(5));
        $this->assertSame(6, RequestValue::int(5.9));
        $this->assertSame(7, RequestValue::int('7'));
        $this->assertSame(0, RequestValue::int('x', 0));
        $this->assertSame(-1, RequestValue::int(null, -1));
    }

    public function testFloatCoercion(): void
    {
        $this->assertSame(1.5, RequestValue::float('1.5'));
        $this->assertSame(2.0, RequestValue::float(2));
        $this->assertSame(99.0, RequestValue::float('junk', 99.0));
    }

    public function testBoolCoercion(): void
    {
        $this->assertTrue(RequestValue::bool('1'));
        $this->assertTrue(RequestValue::bool('true'));
        $this->assertTrue(RequestValue::bool('on'));
        $this->assertFalse(RequestValue::bool('0'));
        $this->assertFalse(RequestValue::bool('off'));
        $this->assertFalse(RequestValue::bool(''));
        $this->assertTrue(RequestValue::bool('maybe', true));
    }
}
