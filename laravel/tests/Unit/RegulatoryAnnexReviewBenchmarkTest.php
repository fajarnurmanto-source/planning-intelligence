<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\Pi\Development\RegulatoryAnnexReviewBenchmark;
use PHPUnit\Framework\TestCase;

final class RegulatoryAnnexReviewBenchmarkTest extends TestCase
{
    private function corpus():array
    {
        $sha=str_repeat('A',64);
        $routes=['LEGAL_LAYOUT_NODES_REVIEW','FISCAL_ROW_FRAGMENTS_REVIEW','LOCATOR_RECONCILIATION_HOLD','HEADING_STRUCTURE_REVIEW'];
        return ['schema'=>'PI_RF04_CORPUS_INVENTORY_V1','source_mode'=>'LOCAL_PINNED_REPOSITORY','adaptive_layout_requested'=>true,'network_requests'=>0,'production_admission'=>'DENIED',
            'documents'=>['fixture'=>['source_sha256'=>$sha,'byte_pin_status'=>'OWNER_OBSERVED_PIN_MATCH','inventory'=>['expected_pages'=>4],
                'format_profile'=>['source_sha256'=>$sha,'numeric_admission'=>0,'production_admission'=>'DENIED','adaptive_layout'=>[
                    'binding'=>'SAME_TEXT_PAGE_HASH_AND_INVENTORY_MARKER_LINE_HASH','pages'=>array_map(static fn($i,$r)=>['page'=>$i+1,'second_pass_route'=>$r],array_keys($routes),$routes)]]]]];
    }
    private function reference(array $classes):array
    {
        $rows=[];
        foreach($classes as $i=>$class)$rows[]=['document_id'=>'fixture','source_sha256'=>str_repeat('A',64),'pdf_page'=>$i+1,'expected_class'=>$class,'review_state'=>'REVIEWED','review_reference'=>'Synthetic fixture, not actual gold'];
        return ['schema'=>'PI_RF05_INDEPENDENT_PAGE_REFERENCE_V1','reference_method'=>'INDEPENDENT_PHYSICAL_PDF_REVIEW','pages'=>$rows];
    }
    public function test_metrics_include_abstention_and_do_not_promote():void
    {
        $r=(new RegulatoryAnnexReviewBenchmark)->evaluate($this->corpus(),$this->reference(['PROVISION','PROVISION','ACCOUNT_FRAGMENT','HEADING']));
        self::assertSame(2,$r['correct']);self::assertSame(1,$r['mismatched']);self::assertSame(1,$r['abstained']);
        self::assertSame(0.5,$r['accuracy_including_abstention']);self::assertSame(0.75,$r['coverage']);
        self::assertEquals(0.5,$r['per_class']['PROVISION']['recall']);self::assertEquals(0,$r['per_class']['ACCOUNT_FRAGMENT']['precision']);
        self::assertNull($r['per_class']['MIXED']['precision']);self::assertSame('NOT_PASSED',$r['gold_gate']);
        self::assertSame('DENIED',$r['production_admission']);
    }
    public function test_review_plan_blind_and_no_reference_no_metric():void
    {
        $b=new RegulatoryAnnexReviewBenchmark;$c=$this->corpus();
        $p=$b->reviewPlan($c,2);self::assertCount(2,$p['pages']);self::assertSame($p,$b->reviewPlan($c,2));
        foreach($p['pages'] as $row){self::assertNull($row['expected_class']);self::assertArrayNotHasKey('second_pass_route',$row);}
        $r=$b->evaluate($c,$this->reference([]));self::assertNull($r['accuracy_including_abstention']);self::assertSame('HOLD_NO_GOLD',$r['state']);
    }
    public function test_reference_sha_drift_rejected():void
    {
        $ref=$this->reference(['PROVISION']);$ref['pages'][0]['source_sha256']=str_repeat('B',64);
        $this->expectExceptionMessage('PI_RF05_REFERENCE_LOCATOR_OR_REVIEW_HOLD');
        (new RegulatoryAnnexReviewBenchmark)->evaluate($this->corpus(),$ref);
    }
    public function test_unreviewed_and_duplicate_reference_rejected():void
    {
        $ref=$this->reference(['PROVISION']);$ref['pages'][]=$ref['pages'][0];
        $this->expectExceptionMessage('PI_RF05_REFERENCE_LOCATOR_OR_REVIEW_HOLD');
        (new RegulatoryAnnexReviewBenchmark)->evaluate($this->corpus(),$ref);
    }
}
