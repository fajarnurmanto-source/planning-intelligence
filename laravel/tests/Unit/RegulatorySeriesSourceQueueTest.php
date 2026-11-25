<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\NormativeVersionTimeline;
use PHPUnit\Framework\TestCase;
final class RegulatorySeriesSourceQueueTest extends TestCase
{
    public function test_real_metadata_queue_never_guesses_effective_dates(): void
    {
        $q=json_decode(file_get_contents(__DIR__.'/../../resources/pi/rf03-rkpd-series-source-queue.json'),true,32,JSON_THROW_ON_ERROR);
        $t=new NormativeVersionTimeline();
        foreach ([2024,2025,2026,2027] as $year) {
            $r=$t->resolveSeries($q['series'],$q['members'],$year,'2026-11-25','2026-11-25');
            self::assertSame('UNKNOWN_TEMPORAL_EVIDENCE_HOLD',$r['state']);
            self::assertCount(1,$r['unknown_ids']);
            self::assertSame(0,$r['implicit_repeals']);
        }
        self::assertSame('DISCOVERY_REQUIRED',$t->resolveSeries($q['series'],$q['members'],2028,'2026-11-25','2026-11-25')['state']);
    }
}
