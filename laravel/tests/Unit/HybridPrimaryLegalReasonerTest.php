<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\Pi\Regulatory\HybridPrimaryLegalReasoner;
use App\Services\Pi\Regulatory\RegulatoryGenealogyGraph;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
use App\Services\Pi\Regulatory\R82ProvisionTextScanner;
use PHPUnit\Framework\TestCase;

final class HybridPrimaryLegalReasonerTest extends TestCase
{
    private function fixture(): array
    {
        // Synthetic text only. Not an official quotation or gold legal benchmark.
        $scan=(new R82ProvisionTextScanner)->scan(
            "Menimbang: untuk melaksanakan ketentuan Pasal 16 perlu menetapkan aturan.\nMengingat:",
            "Pasal 16\nPengaturan contoh untuk menguji kontrak bukti.\nPasal 17\nKetentuan berikutnya.",
            [['article'=>16,'paragraphs'=>[]]]);
        return ['schema'=>'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1','source_snapshot_integrity'=>'R81_TWO_INDEPENDENT_SHA256_PINS_MATCH','production_admission'=>'DENIED',
            'sources'=>[
                ['instrument_id'=>'PP-81-2012','pdf_sha256'=>str_repeat('A',64),'acquisition_url'=>'https://www.peraturan.go.id/files/pp81-2012bt.pdf','text_layer_status'=>'LOCAL_TEXT_LAYER_EXTRACTED'],
                ['instrument_id'=>'UU-18-2008','pdf_sha256'=>str_repeat('B',64),'acquisition_url'=>'https://peraturan.bpk.go.id/Download/28462/fixture.pdf','text_layer_status'=>'LOCAL_TEXT_LAYER_EXTRACTED']],
            'candidates'=>$scan['records']];
    }

    private function reasoner(): HybridPrimaryLegalReasoner
    {
        return new HybridPrimaryLegalReasoner(new RegulatoryGenealogyGraph(new RegulatoryTaxonomyRegistry()));
    }

    public function test_primary_context_and_structural_guard_remain_distinct(): void
    {
        $r=$this->reasoner();
        $preview=$r->preview($this->fixture(),16,'2026-11-26','2026-11-26');
        self::assertCount(3,$preview['context']['evidence']);
        self::assertSame('PREAMBLE_CONTEXT_CANDIDATE',$preview['context']['evidence'][2]['role']);
        self::assertSame('PRIMARY_TEXT_LAYER_CANDIDATES_NOT_CERTIFIED_LEGAL_TEXT',$preview['context']['scope']);
        $out=$r->assess($preview,['semantic_relation'=>'RELATED','semantic_confidence'=>'HIGH','explanation'=>'P1 merujuk P2; bukti pelaksanaan belum cukup.']);
        self::assertSame('SEMANTIC_CANDIDATE_REVIEW_REQUIRED',$out['state']);
        self::assertSame('CITES_AS_BASIS',$out['structural_relation']['type']);
        self::assertSame('ABSTAIN',$out['structural_relation']['state']);
        self::assertSame('TEMPORAL_EVIDENCE_UNKNOWN',$out['structural_relation']['reason']);
        self::assertSame('DENIED',$out['production_admission']);
    }

    public function test_valid_abstention_retains_reason_and_independent_structural_hold(): void
    {
        $r=$this->reasoner();
        $p=$r->preview($this->fixture(),16,'2026-11-26','2026-11-26');
        $v=['semantic_relation'=>'INSUFFICIENT','semantic_confidence'=>'LOW','explanation'=>'P1 cites P2; operative PP text missing.'];
        $out=$r->assess($p,$v);
        self::assertSame('ABSTAIN',$out['state']);
        self::assertSame('INSUFFICIENT_SEMANTICS',$out['reason']);
        self::assertSame('PASS',$out['model_output_validation']);
        self::assertSame($v,$out['semantic_abstention']);
        self::assertNull($out['semantic_candidate']);
        self::assertSame('TEMPORAL_EVIDENCE_UNKNOWN',$out['structural_relation']['reason']);
        self::assertSame('NOT_ASSESSED',$out['legal_effect']);
        self::assertSame('DENIED',$out['production_admission']);
        $v['explanation']=' ';
        self::assertSame('INVALID_MODEL_OUTPUT',$r->assess($p,$v)['reason']);
        self::assertSame('NOT_PASSED',$r->assess($p,$v)['model_output_validation']);
    }

    public function test_preamble_context_hash_drift_blocks_preview(): void
    {
        $f=$this->fixture();
        $f['candidates'][0]['pp_preamble_context']['excerpt'].=' altered';
        $this->expectExceptionMessage('HRIA_PREAMBLE_CONTEXT_HOLD');
        $this->reasoner()->preview($f,16,'2026-11-26','2026-11-26');
    }

    public function test_changed_context_and_unknown_classification_are_rejected(): void
    {
        $r=$this->reasoner();$p=$r->preview($this->fixture(),16,'2026-11-26','2026-11-26');
        $v=['semantic_relation'=>'DELEGATED_BY','semantic_confidence'=>'HIGH','explanation'=>'unsupported'];
        self::assertSame('INVALID_MODEL_OUTPUT',$r->assess($p,$v)['reason']);
        $p['context']['article']=17;
        self::assertSame('PREVIEW_DRIFT',$r->assess($p,$v)['reason']);
    }

    public function test_excerpt_hash_drift_blocks_preview(): void
    {
        $f=$this->fixture();$f['candidates'][0]['uu_passages'][0]['excerpt']='tampered';
        $this->expectExceptionMessage('HRIA_EXCERPT_HOLD');
        $this->reasoner()->preview($f,16,'2026-11-26','2026-11-26');
    }
}
