<?php

declare(strict_types=1);

namespace PerfectApp\WebKit\Tests\Http;

use PerfectApp\WebKit\Http\ApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiResponse::class)]
final class ApiResponseTest extends TestCase
{
    public function testSuccessClassification(): void
    {
        $ok = new ApiResponse(200, [], ['x' => 1], null);
        $this->assertTrue($ok->isSuccess());
        $this->assertFalse($ok->isClientError());
        $this->assertFalse($ok->isServerError());
    }

    public function testClientAndServerErrorClassification(): void
    {
        $notFound = new ApiResponse(404, [], [], null);
        $this->assertTrue($notFound->isClientError());
        $serverErr = new ApiResponse(503, [], [], null);
        $this->assertTrue($serverErr->isServerError());
    }

    public function testBodyMapRejectsLists(): void
    {
        $response = new ApiResponse(200, [], [1, 2, 3], null);
        $this->expectException(\LogicException::class);
        $response->bodyMap();
    }

    public function testBodyListRejectsMaps(): void
    {
        $response = new ApiResponse(200, [], ['a' => 1], null);
        $this->expectException(\LogicException::class);
        $response->bodyList();
    }
}
