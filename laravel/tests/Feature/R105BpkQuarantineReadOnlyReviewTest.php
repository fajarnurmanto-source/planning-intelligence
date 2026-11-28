<?php
namespace Tests\Feature;
use App\Services\Pi\Development\BpkQuarantineReadOnlyReview;
use App\Services\Pi\Development\PrimaryPdfCandidateIntake;
use RuntimeException;
use Tests\TestCase;

final class R105BpkQuarantineReadOnlyReviewTest extends TestCase
{
    private array $roots = [];
    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($root);
        }
        parent::tearDown();
    }
    private function sample(): array
    {
        $root = sys_get_temp_dir().'/pi-r105-'.bin2hex(random_bytes(7));
        mkdir($root);
        $this->roots[] = $root;
        $folder = $root.'/quarantine';
        mkdir($folder);
        $records = [
            ['YOG-APBD-DETAIL-2026','https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf','https://peraturan.bpk.go.id/Details/346297/perwali-kota-yogyakarta-no-74-tahun-2025'],
            ['YOG-APBD-DETAIL-CHANGE3-2026','https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf','https://peraturan.bpk.go.id/Details/350352/perwali-kota-yogyakarta-no-12-tahun-2026']
        ];
        $intake = new PrimaryPdfCandidateIntake(resource_path('pi/official-source-metadata-r100.json'));
        $entries = []; $files = [];
        foreach ($records as $i => [$id,$url,$detail]) {
            $source = $root.'/synthetic-'.$i.'.pdf';
            file_put_contents($source, "%PDF-1.7\n".str_repeat("SYNTHETIC CI ONLY ".$i."\n", 20)."%%EOF\n");
            $stage = $intake->stage($id,$source,$url,'2026-11-24T02:42:00+07:00',$folder,true);
            $entries[]=['source_id'=>$id,'owner_bytes'=>filesize($source),
                'owner_sha256'=>strtoupper(hash_file('sha256',$source)),
                'bpk_pdf_url'=>$url,'bpk_detail_url'=>$detail];
            $files[$id]=$stage;
        }
        $pins=$root.'/pins.json';
        $write=function(array $entries) use ($pins): void {
            file_put_contents($pins,json_encode([
                'schema'=>'PI_R105_OWNER_BPK_BYTE_PINS_V1',
                'byte_pin_origin'=>'OWNER_R104_LOG_NOT_REMOTE_BINARY_VERIFICATION',
                'document_identity'=>'REVIEW_REQUIRED','numeric_admission'=>0,'entries'=>$entries
            ],JSON_THROW_ON_ERROR));
        };
        $write($entries);
        $checker=new BpkQuarantineReadOnlyReview(
            $pins,resource_path('pi/official-source-metadata-r100.json'),
            resource_path('pi/bpk-perwal12-dlh-0004-fiscal-candidate-r103.json'));
        return [$checker,$folder,$files,$entries,$write];
    }
    public function test_readonly_pair_and_candidate_arithmetic_never_admit_numbers(): void
    {
        [$checker,$folder,$files]=$this->sample();
        $before=hash_file('sha256',$files['YOG-APBD-DETAIL-2026']['receipt_path']);
        $result=$checker->inspect($folder);
        $this->assertSame('PASS',$result['local_byte_receipt_integrity']);
        $this->assertSame(1020366250,$result['six_visible_rows_derived_idr']);
        $this->assertSame(0,$result['numeric_admission']);
        $this->assertSame('DENIED',$result['production_admission']);
        $this->assertFalse($result['writes_performed']);
        $this->assertSame($before,hash_file('sha256',$files['YOG-APBD-DETAIL-2026']['receipt_path']));
    }
    public function test_pdf_byte_tampering_fails_closed(): void
    {
        [$checker,$folder,$files]=$this->sample();
        file_put_contents($files['YOG-APBD-DETAIL-2026']['pdf_path'],"\ntamper",FILE_APPEND);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PI_R105_ARCHIVE_SHA_OR_BYTES_MISMATCH');
        $checker->inspect($folder);
    }
    public function test_receipt_source_swap_fails_closed(): void
    {
        [$checker,$folder,$files]=$this->sample();
        $receiptFile=$files['YOG-APBD-DETAIL-2026']['receipt_path'];
        $receipt=json_decode(file_get_contents($receiptFile),true,64,JSON_THROW_ON_ERROR);
        $receipt['source_id']='YOG-APBD-DETAIL-CHANGE3-2026';
        file_put_contents($receiptFile,json_encode($receipt,JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PI_R105_RECEIPT_IDENTITY_OR_GATE_MISMATCH');
        $checker->inspect($folder);
    }
    public function test_swapped_manifest_url_and_ambiguous_archive_are_held(): void
    {
        [$checker,$folder,$files,$entries,$write]=$this->sample();
        $wrong=$entries; $wrong[0]['bpk_pdf_url']=$wrong[1]['bpk_pdf_url']; $write($wrong);
        try { $checker->inspect($folder); $this->fail('Swapped BPK URL admitted'); }
        catch(RuntimeException $e) { $this->assertSame('PI_R105_SOURCE_ID_OR_BPK_URL_MISMATCH',$e->getMessage()); }
        $write($entries);
        file_put_contents(dirname($files['YOG-APBD-DETAIL-2026']['pdf_path']).'/extra.pdf','extra');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PI_R105_AMBIGUOUS_ARCHIVE_HOLD');
        $checker->inspect($folder);
    }
}
