<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
use App\Services\Pi\Regulatory\NormativeVersionTimeline;
use PHPUnit\Framework\TestCase;

final class RegulatorySeriesFoundationTest extends TestCase
{
    private function member(int $year,int $number,?string $effective): array
    {
        return ['jurisdiction'=>'ID','issuer'=>'KEMENDAGRI','kind'=>'PERMENDAGRI','number'=>(string)$number,'year'=>$year-1,
            'series_id'=>'ID:KEMENDAGRI:RKPD-GUIDANCE','target_start_year'=>$year,'target_end_year'=>$year,
            'observed_on'=>'2026-11-25','effective_on'=>$effective,'repealed_on'=>null,
            'source_url'=>'https://peraturan.go.id/id/fixture','source_locator'=>'Synthetic fixture, not verified law'];
    }
    private function series(): array{return ['id'=>'ID:KEMENDAGRI:RKPD-GUIDANCE','cadence'=>'ANNUAL'];}
    public function test_same_number_year_different_issuer_not_same_identity(): void
    {
        $r=new RegulatoryTaxonomyRegistry();$a=$this->member(2026,10,null);
        $b=$a;$b['issuer']='OTHER-MINISTRY';
        self::assertNotSame($r->canonicalIdentity($a),$r->canonicalIdentity($b));
        self::assertSame(['FRAMEWORK','IMPLEMENTING'],$r->functionalRoles(['FRAMEWORK','IMPLEMENTING']));
    }
    public function test_target_period_not_latest_issue_and_no_implicit_repeal(): void
    {
        $r=(new NormativeVersionTimeline)->resolveSeries($this->series(),[$this->member(2026,10,'2025-06-01'),$this->member(2027,14,'2026-06-01')],2026,'2026-11-25','2026-11-25');
        self::assertSame('ONE_CANDIDATE_REVIEW_REQUIRED',$r['state']);
        self::assertSame('OTHER_TARGET_PERIOD',$r['records'][1]['state']);
        self::assertSame(0,$r['implicit_repeals']);
        self::assertSame('DENIED',$r['production_admission']);
    }
    public function test_missing_period_and_unknown_date_abstain(): void
    {
        $t=new NormativeVersionTimeline();$a=$this->member(2026,10,null);
        self::assertSame('DISCOVERY_REQUIRED',$t->resolveSeries($this->series(),[$a],2028,'2026-11-25','2026-11-25')['state']);
        self::assertSame('UNKNOWN_TEMPORAL_EVIDENCE_HOLD',$t->resolveSeries($this->series(),[$a],2026,'2026-11-25','2026-11-25')['state']);
    }
    public function test_knowledge_cutoff_and_multiple_members_hold(): void
    {
        $t=new NormativeVersionTimeline();$a=$this->member(2026,10,'2025-06-01');$b=$this->member(2026,11,'2025-07-01');
        self::assertSame('DISCOVERY_REQUIRED',$t->resolveSeries($this->series(),[$a],2026,'2026-11-25','2025-12-31')['state']);
        self::assertSame('MULTIPLE_CANDIDATES_HOLD',$t->resolveSeries($this->series(),[$a,$b],2026,'2026-11-25','2026-11-25')['state']);
    }
}
