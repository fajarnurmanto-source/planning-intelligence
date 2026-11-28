<?php
namespace Tests\Unit;
use App\Services\Pi\Development\PlanningSemanticCandidates;
use PHPUnit\Framework\TestCase;
final class PlanningSemanticCandidatesTest extends TestCase
{
    private function doc(string $id,string $text): array
    {
        return ['schema'=>'PI_RDI02C_NOMENCLATURE_EVIDENCE_V1','source'=>['id'=>$id],'source_sha256'=>str_repeat('A',64),'numeric_admission'=>0,'production_admission'=>'DENIED','catalogue_identity'=>['instrument'=>'PERWAL','number'=>47,'year'=>2025],'identity_heading_evidence'=>[['page'=>1,'text'=>'PERATURAN WALI KOTA YOGYAKARTA'],['page'=>1,'text'=>'NOMOR 47 TAHUN 2025']],'pages'=>[['page'=>155,'text_sha256'=>str_repeat('B',64)]],'code_occurrences'=>[['page'=>155,'line'=>19,'page_sha256'=>str_repeat('B',64),'code'=>'1.01.01','level'=>'PROGRAM','state'=>'CODE_ROW_CANDIDATE','window'=>[['line'=>19,'text'=>$text,'sha256'=>strtoupper(hash('sha256',$text))]]]]];
    }
    public function test_identity_and_shared_code_do_not_admit_numbers(): void
    {
        $a='YOG-RENSTRA-PD-2025-2029';$b='YOG-RENJA-PD-2026';
        $r=(new PlanningSemanticCandidates)->build([$a=>$this->doc($a,'1.01.01 - PROGRAM  1.000,00  2.000,00  3.000,00  4.000,00  5.000,00'),$b=>$this->doc($b,'1.01.01  PROGRAM  Nilai SPIP  1.000,00')]);
        self::assertTrue($r['documents'][$a]['identity']['heading_match']);
        self::assertSame(1,$r['shared_codes']);
        self::assertSame('FIVE_POSITIONAL_TOKENS',$r['records'][0]['shape']);
        self::assertSame('BLOCKED',$r['crosswalk']['1.01.01']['numeric_comparison']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_tampered_line_holds(): void
    {
        $a='YOG-RENSTRA-PD-2025-2029';$d=$this->doc($a,'1.01.01  PROGRAM');
        $d['code_occurrences'][0]['window'][0]['text']='tampered';
        $this->expectException(\RuntimeException::class);
        (new PlanningSemanticCandidates)->build([$a=>$d]);
    }
}
