<?php
namespace Tests\Unit;
use App\Services\Pi\Development\RegulatoryAnnexReviewBenchmark;
use PHPUnit\Framework\TestCase;
final class RegulatoryAnnexBenchmarkMetricsTest extends TestCase
{
    private function corpus():array {
        $sha=str_repeat('A',64);return ['schema'=>'PI_RF04_CORPUS_INVENTORY_V1','source_mode'=>'LOCAL_PINNED_REPOSITORY','adaptive_layout_requested'=>true,'network_requests'=>0,'production_admission'=>'DENIED','expected_documents'=>2,'holds'=>['missing'=>'PIN_MISSING'],
          'documents'=>['fixture'=>['source_sha256'=>$sha,'byte_pin_status'=>'OWNER_OBSERVED_PIN_MATCH','inventory'=>['expected_pages'=>2],'format_profile'=>['source_sha256'=>$sha,'numeric_admission'=>0,'production_admission'=>'DENIED','adaptive_layout'=>['binding'=>'SAME_TEXT_PAGE_HASH_AND_INVENTORY_MARKER_LINE_HASH','pages'=>[['page'=>1,'second_pass_route'=>'VISUAL_REQUIRED_HOLD'],['page'=>2,'second_pass_route'=>'FISCAL_ROW_FRAGMENTS_REVIEW']]]]]]];
    }
    public function test_visual_routing_success_is_still_extraction_abstention_and_missing_docs_stay_visible():void {
        $pages=[];foreach(['VISUAL','OTHER'] as $i=>$class)$pages[]=['document_id'=>'fixture','source_sha256'=>str_repeat('A',64),'pdf_page'=>$i+1,'expected_class'=>$class,'review_state'=>'REVIEWED','review_reference'=>'Synthetic physical reference fixture'];
        $ref=['schema'=>'PI_RF05_INDEPENDENT_PAGE_REFERENCE_V1','reference_method'=>'INDEPENDENT_PHYSICAL_PDF_REVIEW','reference_identity'=>'AI_ASSISTED_NOT_HUMAN_CERTIFIED','pages'=>$pages];
        $r=(new RegulatoryAnnexReviewBenchmark)->evaluate($this->corpus(),$ref);
        self::assertSame(1,$r['correct']);self::assertEquals(1.0,$r['routing_coverage']);self::assertSame(0.5,$r['deterministic_candidate_route_coverage']);self::assertSame(1,$r['extraction_route_abstentions']);self::assertSame(1,$r['critical_errors']);self::assertFalse($r['corpus_complete']);self::assertSame(2,$r['expected_documents']);self::assertSame(1,$r['processed_documents']);self::assertSame(1,$r['document_processing_holds']);self::assertNull($r['extraction_accuracy']);self::assertSame('NOT_VERIFIED',$r['human_certified_gold']);self::assertSame('DENIED',$r['production_admission']);
    }

    private function summaryCorpus():array {
        $c=$this->corpus();$c['documents']['fixture']['inventory']['pages']=[];
        foreach($c['documents']['fixture']['format_profile']['adaptive_layout']['pages'] as $i=>&$p){
            $hash=str_repeat('B',64);$p+=['text_sha256'=>$hash,'first_pass_route'=>'FORMAT_REVIEW_REQUIRED','hierarchy_locators'=>[],'row_locators'=>[],'unbound_locators'=>[]];
            $c['documents']['fixture']['inventory']['pages'][]=['pdf_page'=>$i+1,'text_sha256'=>$hash,'markers'=>[]];
        }unset($p);return $c;
    }
    public function test_summary_preserves_denominator_and_does_not_label_visual_as_refined_extraction():void {
        $r=(new RegulatoryAnnexReviewBenchmark)->comparisonSummary($this->summaryCorpus());
        self::assertSame(2,$r['physical_pages']);self::assertSame(2,$r['second_pass_pages']);self::assertSame(1,$r['more_specific_candidate_routes']);
        self::assertSame(1,$r['still_requires_alternative_or_visual']);self::assertFalse($r['corpus_complete']);self::assertNull($r['accuracy']);
    }
    public function test_summary_rejects_page_hash_drift():void {
        $c=$this->summaryCorpus();$c['documents']['fixture']['inventory']['pages'][0]['text_sha256']=str_repeat('C',64);
        $this->expectExceptionMessage('PI_RF05_SUMMARY_PAGE_HASH_HOLD');(new RegulatoryAnnexReviewBenchmark)->comparisonSummary($c);
    }
    public function test_summary_rejects_unbound_line_hash():void {
        $c=$this->summaryCorpus();$c['documents']['fixture']['format_profile']['adaptive_layout']['pages'][0]['hierarchy_locators'][]=['line'=>1,'kind'=>'PASAL','line_sha256'=>str_repeat('D',64)];
        $this->expectExceptionMessage('PI_RF05_SUMMARY_LINE_HASH_HOLD');(new RegulatoryAnnexReviewBenchmark)->comparisonSummary($c);
    }
}
