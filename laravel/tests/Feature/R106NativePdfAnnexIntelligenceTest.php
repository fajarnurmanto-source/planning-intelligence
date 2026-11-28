<?php
namespace Tests\Feature;

use App\Services\Pi\Development\NativePdfAnnexIntelligence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use App\Models\User;
use Tests\TestCase;
use RuntimeException;

final class R106NativePdfAnnexIntelligenceTest extends TestCase
{
 use LazilyRefreshDatabase;

 private function fixture(bool $change, bool $missing = false): string
 {
  $heading="PERATURAN WALI KOTA YOGYAKARTA\nNOMOR ".($change?'12 TAHUN 2026':'74 TAHUN 2025')."\n".($change?"PERUBAHAN KETIGA\n":"PENJABARAN APBD\n");
  $body="Sub Kegiatan : 2.11.11.2.01.0004 Peningkatan Peran Serta Masyarakat\n";
  $codes=['5.1.02.01.001.00025','5.1.02.01.001.00026','5.1.02.01.001.00052','5.1.02.02.001.00086','5.1.02.02.009.00013','5.1.02.05.002.00001'];
  $amounts=['18.000.000,00','48.432.250,00','27.060.000,00','48.324.000,00','75.000.000,00','803.550.000,00'];
  foreach($codes as $i=>$code){
   if($missing && $i===5)continue;
   $body.=$change
    ? substr($code,0,-2)." URAIAN BELANJA\n        ".$amounts[$i]."  ".$amounts[$i]."  0,00\n".substr($code,-2)."   URAIAN LANJUTAN\n"
    : $code."  URAIAN BELANJA    ".$amounts[$i]."\n";
  }
  $body.="Halaman ".($change?'520':'335')."\nSub Kegiatan : 2.11.11.2.01.0005 Berikutnya\n";
  return $heading."\f".$body."\f";
 }

 public function test_six_accounts_reconstructed_and_reconciled_without_admission(): void
 {
  $s=new NativePdfAnnexIntelligence;
  $a=$s->parseLayout($this->fixture(false),'74','2025',false);
  $b=$s->parseLayout($this->fixture(true),'12','2026',true);
  $r=$s->reconcileParsed($a,$b);
  $this->assertCount(6,$r['rows']);
  $this->assertSame(2,$r['rows'][0]['base_page']);
  $this->assertSame(2,$r['rows'][0]['change_page']);
  $this->assertSame('5.1.02.01.001.00025',$r['rows'][0]['account_code']);
  $this->assertSame('5.1.02.05.002.00001',$r['rows'][5]['account_code']);
  $this->assertSame(1020366250,$r['derived_leaf_total_idr']);
  $this->assertSame(0,$r['numeric_admission']);
  $this->assertSame('DENIED',$r['production_admission']);
  $this->assertFalse($r['writes_performed']);
 }


 public function test_account_rows_follow_page_breaks_into_next_pdf_page(): void
 {
  $service=new NativePdfAnnexIntelligence;
  $base=$this->fixture(false);
  $base=str_replace("5.1.02.02.009.00013", "\f5.1.02.02.009.00013", $base);
  $parsed=$service->parseLayout($base,'74','2025',false);
  $this->assertSame(2,$parsed['rows']['5.1.02.01.001.00025']['page']);
  $this->assertSame(3,$parsed['rows']['5.1.02.02.009.00013']['page']);
  $this->assertSame(3,$parsed['rows']['5.1.02.05.002.00001']['page']);
 }

 public function test_missing_leaf_rejects(): void
 {
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('PI_R106_SIX_LEAF_ACCOUNTS_NOT_RESOLVED_HOLD');
  (new NativePdfAnnexIntelligence)->parseLayout($this->fixture(true,true),'12','2026',true);
 }

 public function test_wrong_document_heading_rejects(): void
 {
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('PI_R106_PDF_LEGAL_HEADING_IDENTITY_HOLD');
  (new NativePdfAnnexIntelligence)->parseLayout(str_replace('NOMOR 12 TAHUN 2026','NOMOR 42 TAHUN 2026',$this->fixture(true)),'12','2026',true);
 }

 public function test_forged_delta_rejects(): void
 {
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('PI_R106_DUPLICATE_OR_DELTA_MISMATCH_HOLD');
  (new NativePdfAnnexIntelligence)->parseLayout(str_replace('  0,00','  1.000,00',$this->fixture(true)),'12','2026',true);
 }

 public function test_api_auth_tenant_disabled_and_pdf_source_safety(): void
 {
  $url='/api/qa-superior-r106/annex-fiscal-review';
  $this->getJson($url)->assertUnauthorized();
  $this->actingAs(User::factory()->create(['tenant_id'=>'WRONG-TENANT']))
   ->getJson($url)->assertForbidden();
  $this->actingAs(User::factory()->create(['tenant_id'=>'ID-DIY-YOG','role'=>'institution_admin']));
  config()->set('pi.native_annex.enabled',false);
  $this->getJson($url)->assertStatus(503)->assertJsonPath('status','ABSTAIN')
    ->assertJsonPath('numeric_admission',0)->assertJsonPath('production_admission','DENIED');
  $this->get('/api/qa-superior-r106/annex-pdf?source=base')->assertStatus(503)
    ->assertJsonPath('production_admission','DENIED');
  $this->get('/api/qa-superior-r106/annex-pdf?source=../secret')->assertUnprocessable();
  $this->assertDatabaseCount('regulatory_evidence_reviews',0);
 }
}
