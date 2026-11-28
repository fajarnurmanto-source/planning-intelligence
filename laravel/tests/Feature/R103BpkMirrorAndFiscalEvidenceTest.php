<?php

namespace Tests\Feature;

use App\Services\Pi\Development\PrimaryPdfCandidateIntake;
use RuntimeException;
use Tests\TestCase;

final class R103BpkMirrorAndFiscalEvidenceTest extends TestCase
{
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    private function fixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pi-r103-');
        $this->assertIsString($path);
        $this->tmp[] = $path;
        file_put_contents($path,
            "%PDF-1.7\n".str_repeat("SYNTHETIC TEST PDF NOT BPK DOCUMENT\n", 15)."%%EOF\n"
        );
        return $path;
    }

    private function service(): PrimaryPdfCandidateIntake
    {
        return new PrimaryPdfCandidateIntake(
            resource_path('pi/official-source-metadata-r100.json')
        );
    }

    public function test_bpk_official_mirror_is_accepted_only_for_two_pinned_regulations_and_receipts_preserve_republication(): void
    {
        $file = $this->fixture();
        $caseA = $this->service()->stage(
            'YOG-APBD-DETAIL-2026',
            $file,
            'https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf',
            '2026-11-24T02:10:00+07:00',
            sys_get_temp_dir()
        );
        $caseB = $this->service()->stage(
            'YOG-APBD-DETAIL-CHANGE3-2026',
            $file,
            'https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf',
            '2026-11-24T02:10:00+07:00',
            sys_get_temp_dir()
        );

        foreach ([$caseA, $caseB] as $result) {
            $receipt = $result['receipt'];
            $this->assertSame('BPK_OFFICIAL_REPUBLICATION', $receipt['source_repository']);
            $this->assertSame('OFFICIAL_BPK_REPUBLICATION_OF_CITY_LEGAL_RECORD_NOT_ORIGIN_BYTES', $receipt['source_repository_relationship']);
            $this->assertStringStartsWith('https://jdih.jogjakota.go.id/', $receipt['origin_metadata_url']);
            $this->assertStringStartsWith('https://peraturan.bpk.go.id/Details/', $receipt['source_repository_detail_url']);
            $this->assertFalse($receipt['source_file_url_retrieval_confirmed']);
            $this->assertSame('NOT_PRESENT_OFFLINE', $receipt['source_bytes_direct_retrieval_proof']);
            $this->assertSame('REVIEW_REQUIRED', $receipt['document_identity_match']);
            $this->assertSame(0, $receipt['financial_amounts_admitted']);
            $this->assertSame('DENIED', $receipt['production_admission']);
            $this->assertNull($result['pdf_path']);
        }
        $this->assertNotSame(
            $caseA['receipt']['source_repository_detail_url'],
            $caseB['receipt']['source_repository_detail_url']
        );
    }

    public function test_swapped_bpk_regulation_urls_arbitrary_paths_and_redirects_are_rejected(): void
    {
        $file = $this->fixture();
        $validUrlA = 'https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf';
        $validUrlB = 'https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf';
        foreach ([
            ['YOG-APBD-DETAIL-2026', $validUrlB],
            ['YOG-APBD-DETAIL-CHANGE3-2026', $validUrlA],
            ['YOG-RKPD-2026', $validUrlA],
            ['YOG-APBD-DETAIL-2026', $validUrlA.'?redirect=1'],
            ['YOG-APBD-DETAIL-2026', 'https://peraturan.bpk.go.id.attacker.example/Download/409706/file.pdf'],
            ['YOG-APBD-DETAIL-2026', 'https://peraturan.bpk.go.id/Download/999999/file.pdf'],
            ['YOG-APBD-DETAIL-2026', 'http://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf'],
        ] as [$sourceId, $badUrl]) {
            try {
                $this->service()->stage($sourceId, $file, $badUrl, '2026-11-24T02:10:00+07:00', sys_get_temp_dir());
                $this->fail('Unknown provider URL or identity was admitted: '.$badUrl);
            } catch (RuntimeException $exception) {
                $this->assertSame('PI_R102_UNTRUSTED_SOURCE_URL_HOLD', $exception->getMessage());
            }
        }
    }

    public function test_r103_bpk_table_has_six_page_located_rows_and_never_admits_unreviewed_budget(): void
    {
        $path = resource_path('pi/bpk-perwal12-dlh-0004-fiscal-candidate-r103.json');
        $ledger = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);

        $this->assertSame('PI_R103_BPK_PDF_VIEW_FISCAL_CANDIDATE_V1', $ledger['schema']);
        $this->assertSame('YOG-APBD-DETAIL-CHANGE3-2026', $ledger['source_id']);
        $this->assertSame('2.11.11.2.01.0004', $ledger['subactivity_code']);
        $this->assertSame(560, $ledger['source_pdf_page_index_zero_based']);
        $this->assertSame(561, $ledger['source_pdf_page_one_based']);
        $this->assertSame(520, $ledger['printed_appendix_page']);
        $this->assertNull($ledger['source_pdf_sha256']);
        $this->assertFalse($ledger['source_pdf_full_bytes_acquired']);
        $this->assertCount(6, $ledger['rows']);
        $this->assertSame(1020366250, array_sum(array_column($ledger['rows'], 'before_idr')));
        $this->assertSame(1020366250, array_sum(array_column($ledger['rows'], 'after_idr')));
        $this->assertSame(0, array_sum(array_column($ledger['rows'], 'delta_idr')));
        $this->assertSame(1020366250, $ledger['derived_totals']['after_idr']);
        $this->assertSame('SUM_ONLY_OF_SIX_VISIBLE_LEAF_EXPENDITURE_LINES_NOT_PRINTED_TOTAL',
            $ledger['derived_totals']['calculation']);
        $this->assertFalse($ledger['interpretation']['agency_dlh_in_named_change_agencies']);
        $this->assertFalse($ledger['interpretation']['value_admitted_to_fiscal_engine']);
        $this->assertFalse($ledger['interpretation']['reviewer_approved']);
        $this->assertSame('DENIED', $ledger['interpretation']['production_admission']);
        $this->assertSame('REQUIRED', $ledger['interpretation']['fifth_amendment_check']);
        $this->assertSame('NOT_AVAILABLE', $ledger['interpretation']['realization_data']);
    }

    public function test_existing_jdih_offline_path_still_reads_without_bpk_mirror_provenance(): void
    {
        $result = $this->service()->stage(
            'YOG-APBD-DETAIL-2026',
            $this->fixture(),
            'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf',
            '2026-11-24T02:10:00+07:00',
            sys_get_temp_dir()
        );

        $this->assertSame('JDIH_KOTA_YOGYAKARTA', $result['receipt']['source_repository']);
        $this->assertSame('LOCAL_GOVERNMENT_ORIGIN_CLAIM_NOT_FETCH_VERIFIED',
            $result['receipt']['source_repository_relationship']);
        $this->assertSame(0, $result['receipt']['financial_amounts_admitted']);
    }
}
