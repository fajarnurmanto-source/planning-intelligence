<?php
namespace Tests\Unit;
use App\Services\Pi\Development\FiscalContextDiagnostics;
use App\Services\Pi\Development\FiscalOrganizationContext;
use App\Services\Pi\Development\FullDocumentInventory;
use App\Services\Pi\Development\MultilineFiscalTableReconstructor;
use PHPUnit\Framework\TestCase;
final class FiscalContextDiagnosticsTest extends TestCase
{
    private function fixture(): array
    {
        $text="Organisasi: DLH\nSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.001.000 Barang\n100,00  100,00  0,00\n25   Lanjutan\n5.1.02.01.001.001 Barang\n\f";
        $doc=(new FullDocumentInventory)->inventory($text,1);
        $doc['multiline_fiscal']=(new MultilineFiscalTableReconstructor)->reconstruct($text,1,true);
        $doc['fiscal_context']=(new FiscalOrganizationContext)->resolve($text,$doc['multiline_fiscal']);
        return $doc;
    }
    public function test_intersection_and_missing_levels_are_measured(): void
    {
        $r=(new FiscalContextDiagnostics)->inspect($this->fixture());
        self::assertSame(2,$r['fragments']);
        self::assertSame(1,$r['suffix_token_intersection']['SUFFIX_HOLD|TOKEN_HOLD']);
        self::assertSame(1,$r['suffix_token_intersection']['SUFFIX_OK|TOKEN_OK']);
        self::assertSame(2,$r['missing_levels']['unit']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_locator_tampering_is_rejected(): void
    {
        $doc=$this->fixture();
        $doc['fiscal_context']['records'][0]['line']=99;
        $this->expectException(\RuntimeException::class);
        (new FiscalContextDiagnostics)->inspect($doc);
    }
}
