<?php
namespace Tests\Unit;

use App\Services\Pi\Development\FiscalRecordReconstruction;
use App\Services\Pi\Development\FullDocumentInventory;
use App\Services\Pi\Development\DocumentLayoutReconstructor;
use PHPUnit\Framework\TestCase;

final class FiscalRecordReconstructionTest extends TestCase
{
    private function runCase(string $text,bool $amendment=false): array
    {
        $doc=(new FullDocumentInventory)->inventory($text,1);
        $doc['source_sha256']=str_repeat('A',64);
        $doc['layout_candidates']=(new DocumentLayoutReconstructor)->reconstruct($text,1);
        return (new FiscalRecordReconstruction)->reconstruct($doc,$amendment);
    }
    public function test_exact_minor_units_and_split_hold(): void
    {
        $r=$this->runCase("Sub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.00001  barang  1.234,56\n5.1.02.01.01.001  barang  12,00\n\f");
        self::assertSame(1,$r['row_shape_candidates']);
        self::assertSame(123456,$r['records'][0]['positional_minor_units'][0]);
        self::assertContains('SPLIT_CODE_NEEDS_CONTINUATION',$r['records'][1]['reasons']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_delta_mismatch_and_duplicates_are_held(): void
    {
        $r=$this->runCase("Sub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.00001  barang  10,00  12,00  2,00\n5.1.02.01.01.00001  barang  10,00  12,00  3,00\n\f",true);
        self::assertSame(0,$r['row_shape_candidates']);
        self::assertSame(2,$r['hold_reason_counts']['REPEATED_SUBACTIVITY_ACCOUNT_IDENTITY']);
        self::assertSame(1,$r['hold_reason_counts']['POSITIONAL_DELTA_MISMATCH']);
    }
    public function test_different_subactivities_do_not_collapse_and_legacy_is_held(): void
    {
        $text="Sub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.00001  barang  1,00\nSub Kegiatan: 2.11.11.2.01.0005\n5.1.02.01.01.00001  barang  2,00\n\f";
        $r=$this->runCase($text);
        self::assertSame(2,$r['row_shape_candidates']);
        self::assertSame('2.11.11.2.01.0004',$r['records'][0]['subactivity_candidate']);
        self::assertSame('2.11.11.2.01.0005',$r['records'][1]['subactivity_candidate']);
        $doc=(new FullDocumentInventory)->inventory($text,1);
        $doc['source_sha256']=str_repeat('A',64);
        $doc['layout_candidates']=(new DocumentLayoutReconstructor)->reconstruct($text,1);
        $doc['layout_candidates']['schema']='PI_RDI_LAYOUT_CANDIDATES_V1';
        $old=(new FiscalRecordReconstruction)->reconstruct($doc,false);
        self::assertSame(0,$old['row_shape_candidates']);
        self::assertSame(2,$old['hold_reason_counts']['LEGACY_SCOPE_REEXTRACTION_REQUIRED']);
    }
}
