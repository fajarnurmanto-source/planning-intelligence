<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\RegulatoryAcquisitionRetryPolicy;
use PHPUnit\Framework\TestCase;
final class RegulatoryAcquisitionRetryPolicyTest extends TestCase
{
    public function test_retry_after_and_circuit(): void
    {
        $p=new RegulatoryAcquisitionRetryPolicy();
        self::assertSame(1800,$p->decide(429,1,1000,'800')['retry_at']);
        self::assertTrue($p->decide(503,3,1000)['circuit_open']);
        self::assertSame(1900,$p->decide(503,3,1000)['retry_at']);
        self::assertSame(4600,$p->decide(0,100,1000)['retry_at']);
        self::assertSame(2000,$p->decide(429,1,1000,'Thu, 01 Jan 1970 00:33:20 GMT')['retry_at']);
    }
    public function test_permanent_errors_do_not_retry(): void
    {
        $p=new RegulatoryAcquisitionRetryPolicy();
        self::assertSame('ACCESS_DENIED_ALTERNATE_REQUIRED',$p->decide(403,1,1000)['action']);
        self::assertSame('REDISCOVERY_REQUIRED',$p->decide(404,1,1000)['action']);
        self::assertNull($p->decide(404,1,1000)['retry_at']);
        self::assertFalse($p->decide(200,1,1000)['automatic_retry']);
    }
}
