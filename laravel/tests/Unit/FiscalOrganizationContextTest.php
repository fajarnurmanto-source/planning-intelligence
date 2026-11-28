<?php
namespace Tests\Unit;
use App\Services\Pi\Development\FiscalOrganizationContext;
use App\Services\Pi\Development\MultilineFiscalTableReconstructor;
use PHPUnit\Framework\TestCase;
final class FiscalOrganizationContextTest extends TestCase
{
    private function runCase(string $text,int $pages): array
    {
        return (new FiscalOrganizationContext)->resolve($text,(new MultilineFiscalTableReconstructor)->reconstruct($text,$pages,false));
    }
    public function test_carry_requires_matching_header_and_consecutive_printed_page(): void
    {
        $text="Halaman 1\nOrganisasi: DLH\nUnit Organisasi: A\nProgram: P\nKegiatan: K\nSub Kegiatan: 2.11.11.2.01.0004\nKode Rekening  Uraian  Jumlah\n5.1.02.01.001.00025  barang  100,00\n\fHalaman 2\nKode Rekening  Uraian  Jumlah\n5.1.02.01.001.00026  barang  100,00\n\fHalaman 4\nKode Rekening  Uraian  Jumlah\n5.1.02.01.001.00027  barang  100,00\n\f";
        $r=$this->runCase($text,3);
        self::assertSame(3,$r['fragment_count']);
        self::assertSame(1,$r['cross_page_candidates']);
        self::assertSame('DLH',$r['records'][1]['context']['organization']['value']);
        self::assertSame(1,$r['records'][1]['context']['organization']['page']);
        self::assertSame('CONTEXT_HOLD',$r['records'][2]['state']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_new_unit_invalidates_descendants_and_preserves_opd(): void
    {
        $r=$this->runCase("Organisasi: DLH\nUnit Organisasi: A\nSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.001.00025  barang  100,00\nUnit Organisasi: B\n5.1.02.01.001.00025  barang  100,00\nSub Kegiatan: 2.11.11.2.01\n5.1.02.01.001.00025  barang  100,00\n\f",1);
        self::assertSame('DLH',$r['records'][1]['context']['organization']['value']);
        self::assertSame('B',$r['records'][1]['context']['unit']['value']);
        self::assertArrayNotHasKey('subactivity',$r['records'][1]['context']);
        self::assertSame(2,$r['held']);
    }
    public function test_numbered_heading_and_discovery_window(): void
    {
        $text="1.   Urusan Pemerintahan : 4.01 – SEKRETARIAT DAERAH\nOrganisasi: DLH\nSub Kegiatan: 2.11.11.2.01.0004\n4.1.01.09.001.000 Pajak Reklame\n\n25  100,00  100,00  0,00\n\f";
        $m=(new MultilineFiscalTableReconstructor)->reconstruct($text,1,true);
        $s=new FiscalOrganizationContext;
        $c=$s->resolve($text,$m);
        self::assertSame('4.01 – SEKRETARIAT DAERAH',$c['records'][0]['context']['urusan']['value']);
        $r=$s->enrich($text,$m,$c,dirname(__DIR__,2).'/resources/pi/affairs-nomenclature-seed-r87.json');
        self::assertSame(1,$r['registry_matches']);
        self::assertSame('2.11.11',$r['records'][0]['prefix_candidates']['program']);
        self::assertSame(1,$r['next_line_groups']->BLANK);
        self::assertSame('',$r['samples']->BLANK['window'][3]['text']);
        self::assertFalse($r['national_master_verified']);
    }
}
