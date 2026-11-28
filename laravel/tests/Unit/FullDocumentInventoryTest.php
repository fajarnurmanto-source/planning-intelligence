<?php
namespace Tests\Unit;

use App\Services\Pi\Development\FullDocumentInventory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FullDocumentInventoryTest extends TestCase
{
    public function test_physical_pages_and_candidate_zones_are_preserved(): void
    {
        $r=(new FullDocumentInventory)->inventory("Pasal 5A\n(1) Ketentuan\n a. isi\n\f\fPENJELASAN\nPasal 5A\n\fLAMPIRAN II\nSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.001\n\f",4);
        self::assertSame(4,$r['inventoried_pages']);
        self::assertSame(1,$r['no_text_pages']);
        self::assertSame('NO_TEXT_OCR_OR_VISUAL_REQUIRED',$r['pages'][1]['state']);
        self::assertSame('EXPLANATION_HEADING',$r['pages'][2]['markers'][1]['zone_signal']);
        self::assertSame(2,$r['marker_counts']['ARTICLE']);
        self::assertSame(1,$r['marker_counts']['ACCOUNT_SPLIT_MARKER']);
        self::assertSame('NOT_PERFORMED',$r['structure_verification']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_mismatched_physical_count_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new FullDocumentInventory)->inventory("Pasal 1\f",2);
    }
    public function test_empty_final_physical_page_is_not_discarded(): void
    {
        $r=(new FullDocumentInventory)->inventory("Pasal 1\f\f",2);
        self::assertSame(1,$r['no_text_pages']);
    }

    public function test_visual_footer_and_fragmented_glyphs_are_not_usable_native_text(): void
    {
        $text="2023, No.144\n-37-\nwww.peraturan.go.id\n\f.. c ~ ~8 8 !;\n f. -\n .l! c ~\n\fPasal 1\nKetentuan berlaku\n\f";
        $r=(new FullDocumentInventory)->inventory($text,3);
        self::assertSame('SPARSE_OR_FRAGMENTED_REQUIRES_VISUAL',$r['pages'][0]['native_text_quality']['state']);
        self::assertSame('SPARSE_OR_FRAGMENTED_REQUIRES_VISUAL',$r['pages'][1]['native_text_quality']['state']);
        self::assertSame('UNVERIFIED_TEXT_AVAILABLE',$r['pages'][2]['native_text_quality']['state']);
        self::assertSame(strtoupper(hash('sha256',explode("\f",$text)[0])),$r['pages'][0]['text_sha256']);
    }
}
