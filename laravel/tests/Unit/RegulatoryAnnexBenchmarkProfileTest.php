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
        self::assertSame(0,$r['gold_records']);
        self::assertNull($r['recall']);
        self::assertSame('DENIED',$r['production_admission']);
    }
}
