<?php
namespace Tests\Unit;
use App\Services\Pi\Development\FullDocumentInventory;
use App\Services\Pi\Development\RegulatoryAnnexBenchmarkProfile;
use PHPUnit\Framework\TestCase;
final class RegulatoryAnnexBenchmarkProfileTest extends TestCase
{
    public function test_format_signals_do_not_create_gold_or_certification(): void
    {
        $i=['source_sha256'=>str_repeat('A',64)]+(new FullDocumentInventory)->inventory("Pasal 1\n\flampiran\nA    B    C\n\f\f",3);
        $r=(new RegulatoryAnnexBenchmarkProfile)->profile($i);
        self::assertSame(1,$r['counts']['ARTICLE_MARKERS']);
        self::assertSame(1,$r['counts']['TABLE_LAYOUT_MARKERS']);
        self::assertSame(1,$r['counts']['VISUAL_INSPECTION_REQUIRED']);
        self::assertSame(['PROVISION_STRUCTURE_CANDIDATE','TABLE_RECONSTRUCTION_CANDIDATE','VISUAL_OR_OCR_REQUIRED'],array_column($r['pages'],'processing_route'));
        self::assertSame(3,array_sum($r['processing_route_counts']));
        self::assertSame(0,$r['gold_records']);
        self::assertNull($r['recall']);
        self::assertSame('DENIED',$r['production_admission']);
    }
    public function test_second_pass_reuses_layout_without_promoting_or_rewriting_first_pass(): void
    {
        $text="MEMUTUSKAN:\nPasal 1\n(1) Ketentuan contoh\n\fSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.00001   Contoh   1.000,00\n\fBAB II\n\f\f";
        $inventory=['source_sha256'=>str_repeat('A',64)]+(new FullDocumentInventory)->inventory($text,4);
        $baseline=(new RegulatoryAnnexBenchmarkProfile)->profile($inventory);
        $inventory['layout_candidates']=(new \App\Services\Pi\Development\DocumentLayoutReconstructor)->reconstruct($text,4);
        $result=(new RegulatoryAnnexBenchmarkProfile)->profile($inventory);
        self::assertSame($baseline['processing_route_counts'],$result['processing_route_counts']);
        self::assertSame(['LEGAL_LAYOUT_NODES_REVIEW','FISCAL_ROW_FRAGMENTS_REVIEW','HEADING_STRUCTURE_REVIEW','VISUAL_REQUIRED_HOLD'],array_column($result['adaptive_layout']['pages'],'second_pass_route'));
        self::assertSame(4,array_sum($result['adaptive_layout']['route_counts']));
        self::assertSame(0,$result['numeric_admission']);
        self::assertSame('DENIED',$result['production_admission']);
        self::assertNull($result['precision']);
        $inventory['layout_candidates']['account_fragment_pages'][0]['rows'][0]['page']=99;
        $this->expectExceptionMessage('PI_RF05_ROW_LOCATOR_HOLD');
        (new RegulatoryAnnexBenchmarkProfile)->profile($inventory);
    }
    public function test_second_pass_never_binds_same_page_number_from_different_text(): void
    {
        $a="MEMUTUSKAN:\nPasal 1\nKetentuan A.\n\f";
        $b="MEMUTUSKAN:\nPasal 1\nKetentuan B.\n\f";
        $inventory=['source_sha256'=>str_repeat('A',64)]+(new FullDocumentInventory)->inventory($a,1);
        $inventory['layout_candidates']=(new \App\Services\Pi\Development\DocumentLayoutReconstructor)->reconstruct($b,1);
        $this->expectExceptionMessage('PI_RF05_LAYOUT_PAGE_HASH_DRIFT');
        (new RegulatoryAnnexBenchmarkProfile)->profile($inventory);
    }

    public function test_wrong_line_hash_is_held_not_counted_as_bound_candidate(): void
    {
        $text="MEMUTUSKAN:\nPasal 1\nKetentuan contoh.\n\f";
        $inventory=['source_sha256'=>str_repeat('A',64)]+(new FullDocumentInventory)->inventory($text,1);
        $inventory['layout_candidates']=(new \App\Services\Pi\Development\DocumentLayoutReconstructor)->reconstruct($text,1);
        $inventory['layout_candidates']['hierarchy_nodes'][0]['line_sha256']=str_repeat('F',64);
        $r=(new RegulatoryAnnexBenchmarkProfile)->profile($inventory);
        self::assertSame('LOCATOR_RECONCILIATION_HOLD',$r['adaptive_layout']['pages'][0]['second_pass_route']);
        self::assertSame([],$r['adaptive_layout']['pages'][0]['hierarchy_locators']);
        self::assertSame(1,$r['adaptive_layout']['unbound_locator_count']);
        self::assertSame(0,$r['numeric_admission']);
    }
}
