<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\NormativeVersionTimeline;
use PHPUnit\Framework\TestCase;
final class RegulatorySeriesMetadataAcceptanceTest extends TestCase
{
    public function test_observed_metadata_selects_target_period_without_legal_admission(): void
    {
        $q=json_decode(file_get_contents(__DIR__.'/../../resources/pi/rf03-rkpd-series-metadata.json'),true,32,JSON_THROW_ON_ERROR);
        $t=new NormativeVersionTimeline();
        foreach ($q['members'] as $m) {
            $r=$t->resolveSeries($q['series'],$q['members'],$m['target_start_year'],'2026-11-26','2026-11-26');
            self::assertSame('ONE_CANDIDATE_REVIEW_REQUIRED',$r['state']);
            self::assertCount(1,$r['candidate_ids']);
            self::assertStringEndsWith(':'.$m['number'].':'.$m['year'],$r['candidate_ids'][0]);
            self::assertSame(0,$r['implicit_repeals']);
            self::assertSame('NOT_ASSESSED',$r['legal_conclusion']);
            self::assertSame('DENIED',$r['production_admission']);
            self::assertFalse($r['census_complete']);
            self::assertSame('OFFICIAL_METADATA',$m['effective_date_basis']);
            self::assertNull($m['source_sha256']);
            $before=(new \DateTimeImmutable($m['effective_on']))->modify('-1 day')->format('Y-m-d');
            self::assertSame('DISCOVERY_REQUIRED',$t->resolveSeries($q['series'],$q['members'],$m['target_start_year'],$before,'2026-11-26')['state']);
        }
        self::assertSame('DISCOVERY_REQUIRED',$t->resolveSeries($q['series'],$q['members'],2028,'2026-11-26','2026-11-26')['state']);
        self::assertSame('DISCOVERY_REQUIRED',$t->resolveSeries($q['series'],$q['members'],2026,'2026-11-26','2026-11-25')['state']);
    }
}
