<?php
namespace Tests\Unit;
use App\Services\Pi\Development\MultilineFiscalTableReconstructor;
use PHPUnit\Framework\TestCase;
final class MultilineFiscalTableReconstructorTest extends TestCase
{
    public function test_r106_style_split_and_exact_delta(): void
    {
        $r=(new MultilineFiscalTableReconstructor)->reconstruct("Organisasi: DLH\nSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.001  Barang\n25  100,00  150,00  50,00\n5.1.02.01.01.002  Barang\n26  100,00  150,00  49,00\n\f",1,true);
        self::assertSame(2,$r['fragment_count']);
        self::assertSame('5.1.02.01.01.00125',$r['records'][0]['account_code_candidate']);
        self::assertSame([10000,15000,5000],$r['records'][0]['positional_minor_units']);
        self::assertSame(1,$r['row_shape_candidates']);
        self::assertContains('POSITIONAL_DELTA_MISMATCH',$r['records'][1]['reasons']);
    }
    public function test_boundary_and_page_carry_do_not_certify_scope(): void
    {
        $r=(new MultilineFiscalTableReconstructor)->reconstruct("Halaman 1\nSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.001\n\fHalaman 2\n5.1.02.01.01.002\n25  100,00  100,00  0,00\nOrganisasi: Baru\n5.1.02.01.01.003\n26  100,00  100,00  0,00\n\f",2,true);
        self::assertNull($r['records'][0]['account_code_candidate']);
        self::assertSame('2.11.11.2.01.0004',$r['records'][1]['previous_page_scope_candidate']);
        self::assertNull($r['records'][1]['subactivity_same_page']);
        self::assertNull($r['records'][2]['previous_page_scope_candidate']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_r106_amount_before_suffix_and_conflicting_suffixes(): void
    {
        $text="Organisasi: DLH\nSub Kegiatan : 2.11.11.2.01.0004 Peningkatan\n5.1.02.01.001.000 URAIAN BELANJA\n        18.000.000,00  18.000.000,00  0,00\n25   URAIAN LANJUTAN\n5.1.02.01.001.001 URAIAN\n        100,00  100,00  0,00\n26   LANJUTAN\n27   KONFLIK\n\f";
        $r=(new MultilineFiscalTableReconstructor)->reconstruct($text,1,true);
        self::assertSame('5.1.02.01.001.00025',$r['records'][0]['account_code_candidate']);
        self::assertSame('2.11.11.2.01.0004',$r['records'][0]['subactivity_same_page']);
        self::assertNull($r['records'][1]['account_code_candidate']);
        self::assertContains('SPLIT_SUFFIX_UNRESOLVED',$r['records'][1]['reasons']);
        self::assertSame(0,$r['numeric_admission']);
    }
}
