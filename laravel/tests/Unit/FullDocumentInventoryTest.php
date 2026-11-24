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
}
