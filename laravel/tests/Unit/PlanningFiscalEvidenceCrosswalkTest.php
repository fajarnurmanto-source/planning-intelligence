<?php
namespace Tests\Unit;
use App\Services\Pi\Development\PlanningFiscalEvidenceCrosswalk;
use PHPUnit\Framework\TestCase;
final class PlanningFiscalEvidenceCrosswalkTest extends TestCase
{
    public function test_exact_code_with_unresolved_agency_does_not_admit(): void
    {
        $id='YOG-RENJA-PD-2026';$code='2.11.11.2.01.0004';$hash=str_repeat('A',64);
        $loc=['page'=>1,'line'=>2,'page_sha256'=>$hash];
        $p=['schema'=>'PI_RDI02D_SEMANTIC_CANDIDATES_V1','numeric_admission'=>0,'production_admission'=>'DENIED','registry'=>[$code=>[$id=>[$loc]]],'registry_unique_codes'=>1,'records'=>[$loc+['source_id'=>$id,'code'=>$code,'shape'=>'ANNUAL_RAW_CELLS']]];
        $row=['page'=>1,'line'=>3,'page_text_sha256'=>$hash,'account_code_candidate'=>'5.1.02.01.001.00025'];
        $ctx=$row+['context'=>['subactivity'=>['value'=>$code.' Sampah','valid'=>true]],'missing_levels'=>['organization']];
        $d=['source_sha256'=>$hash,'pages'=>[['pdf_page'=>1,'text_sha256'=>$hash]],'multiline_fiscal'=>['schema'=>'PI_RDI_MULTILINE_FISCAL_V2','numeric_admission'=>0,'production_admission'=>'DENIED','fragment_count'=>1,'records'=>[$row]],'fiscal_context'=>['schema'=>'PI_RDI_FISCAL_CONTEXT_V1','numeric_admission'=>0,'production_admission'=>'DENIED','records'=>[$ctx]]];
        $f=['schema'=>'PI_RDI_01A_CORPUS_RECEIPT_V1','processed_documents'=>4,'holds'=>[],'numeric_admission'=>0,'production_admission'=>'DENIED','documents'=>['YOG-APBD-DETAIL-2026'=>$d,'YOG-APBD-DETAIL-CHANGE3-2026'=>$d]];
        $r=(new PlanningFiscalEvidenceCrosswalk)->build($p,$f);
        self::assertSame(1,$r['matched_unique_codes']);
        self::assertSame(2,count($r['records']));
        self::assertSame('EXACT_CODE_ONE_PLAN',$r['records'][0]['state']);
        self::assertNull($r['records'][0]['organization_source']);
        self::assertSame('BLOCKED',$r['numeric_comparison']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_admitted_input_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PlanningFiscalEvidenceCrosswalk)->build(['numeric_admission'=>1],[]);
    }
}
