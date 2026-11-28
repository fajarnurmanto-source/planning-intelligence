<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\RegulatoryFreshnessAssessment;
use PHPUnit\Framework\TestCase;
final class RegulatoryFreshnessAssessmentTest extends TestCase
{
    public function test_recent_bytes_never_prove_current_law(): void
    {
        $f=new RegulatoryFreshnessAssessment;$pin=str_repeat('A',64);
        $o=['observed_at'=>1000,'sha256'=>$pin,'status'=>'OFFICIAL_BYTES_OBSERVED'];
        $r=$f->assess($pin,$o,1050,60);
        self::assertSame('RECENT_MATCHING_BYTES',$r['state']);
        self::assertSame('NOT_ASSESSED',$r['legal_effect']);
        self::assertSame('REFRESH_DUE',$f->assess($pin,$o,1060,60)['state']);
        self::assertSame('DIFFERENT_BYTES_REVIEW_REQUIRED',$f->assess($pin,array_replace($o,['sha256'=>str_repeat('B',64)]),1050,60)['state']);
        self::assertSame('OBSERVATION_TIME_HOLD',$f->assess($pin,$o,999,60)['state']);
        self::assertSame('CHECK_REQUIRED',$f->assess($pin,null,1050,60)['state']);
    }
}
