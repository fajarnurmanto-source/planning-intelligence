<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use App\Services\Pi\Regulatory\R82ProvisionTextScanner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class R82NationalPassageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $directory) {
            foreach (glob($directory.'/pdf/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory.'/pdf');
            @unlink($directory.'/observed-manifest.json');
            @rmdir($directory);
        }
        parent::tearDown();
    }

    /** @return list<array{article:int,paragraphs:list<int>}> */
    private function candidates(): array
    {
        return [
            ['article' => 11, 'paragraphs' => [2]],
            ['article' => 16, 'paragraphs' => []],
            ['article' => 20, 'paragraphs' => [5]],
            ['article' => 22, 'paragraphs' => [2]],
            ['article' => 24, 'paragraphs' => [3]],
            ['article' => 25, 'paragraphs' => [3, 4]],
            ['article' => 28, 'paragraphs' => [3]],
        ];
    }

    private function pp(): string
    {
        return "PERATURAN PEMERINTAH NOMOR 81 TAHUN 2012\nMenimbang : bahwa untuk melaksanakan ".
            "Pasal 11 ayat (2), Pasal 16, Pasal 20 ayat (5), ".
            "Pasal 22 ayat (2), Pasal 24 ayat (3), ".
            "Pasal 25 ayat (3) dan ayat (4), dan Pasal 28 ayat (3) ".
            "Undang-Undang Nomor 18 Tahun 2008 perlu menetapkan Peraturan Pemerintah.\n".
            "Mengingat : Undang-Undang Nomor 18 Tahun 2008.\f";
    }

    private function uu(): string
    {
        return "UNDANG-UNDANG NOMOR 18 TAHUN 2008\n".
            "Pasal 11\n(1) Isi contoh satu.\n(2) Isi contoh dua.\n".
            "Pasal 16\nKetentuan contoh pasal 16.\f".
            "Pasal 20\n(1) Isi contoh.\n(5) Isi contoh lima.\n".
            "Pasal 22\n(2) Isi contoh dua.\n".
            "Pasal 24\n(3) Isi contoh tiga.\n".
            "Pasal 25\n(3) Isi contoh tiga.\n(4) Isi contoh empat.\n".
            "Pasal 28\n(3) Isi contoh tiga.\n".
            "P E N J E L A S A N\nPasal 99\n(1) Hanya penjelasan.\n";
    }

    public function test_scanner_extracts_seven_review_only_candidate_pairs_with_pages_and_hashes(): void
    {
        $result = app(R82ProvisionTextScanner::class)
            ->scan($this->pp(), $this->uu(), $this->candidates());
        $this->assertSame(7, $result['extracted']);
        $this->assertSame(0, $result['unresolved']);
        $this->assertCount(7, $result['records']);
        $this->assertSame('TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED',
            $result['records'][5]['evidence_status']);
        $this->assertCount(2, $result['records'][5]['uu_passages']);
        $this->assertSame(2, $result['records'][2]['uu_passages'][0]['pdf_page']);
        foreach ($result['records'] as $row) {
            $this->assertSame('REVIEW_REQUIRED', $row['review_status']);
            $this->assertSame('NOT_PERFORMED', $row['relation_admission']);
            $this->assertNotNull($row['pp_reference']);
            $this->assertSame(
                strtoupper(hash('sha256', $row['pp_reference']['excerpt'])),
                $row['pp_reference']['excerpt_sha256']
            );
            foreach ($row['uu_passages'] as $passage) {
                $this->assertSame(
                    strtoupper(hash('sha256', $passage['excerpt'])),
                    $passage['excerpt_sha256']
                );
            }
        }
    }

    public function test_wrong_paragraph_and_explanation_only_articles_are_not_admitted(): void
    {
        $uu = str_replace("(4) Isi contoh empat.\n", '', $this->uu());
        $result = app(R82ProvisionTextScanner::class)
            ->scan($this->pp(), $uu, $this->candidates());
        $this->assertSame(6, $result['extracted']);
        $this->assertSame(1, $result['unresolved']);
        $this->assertSame('ABSTAIN_INCOMPLETE_OR_AMBIGUOUS_TEXT',
            $result['records'][5]['evidence_status']);

        $explanationOnly = "PENJELASAN\nPasal 16\nPenjelasan bukan batang tubuh.\n";
        $result = app(R82ProvisionTextScanner::class)
            ->scan($this->pp(), $explanationOnly, [['article' => 16, 'paragraphs' => []]]);
        $this->assertSame(0, $result['extracted']);
        $this->assertSame(0, $result['records'][0]['article_heading_count']);
    }

    public function test_duplicate_main_text_heading_and_missing_pp_reference_abstain(): void
    {
        $uu = "Pasal 16\nSatu.\nPasal 16\nDua.\n";
        $result = app(R82ProvisionTextScanner::class)
            ->scan($this->pp(), $uu, [['article' => 16, 'paragraphs' => []]]);
        $this->assertSame(0, $result['extracted']);
        $this->assertSame(2, $result['records'][0]['article_heading_count']);

        $result = app(R82ProvisionTextScanner::class)
            ->scan("Dokumen tanpa Menimbang\n", $this->uu(), $this->candidates());
        $this->assertSame(7, $result['unresolved']);
    }

    public function test_r81_sha_pins_reject_synthetic_bytes_and_manifest_tampering(): void
    {
        $root = sys_get_temp_dir().'/pi-r82-'.bin2hex(random_bytes(8));
        $this->tempDirs[] = $root;
        mkdir($root.'/pdf', 0755, true);
        $uu = '%PDF-1.4'.str_repeat('U', 300).'%%EOF';
        $pp = '%PDF-1.4'.str_repeat('P', 300).'%%EOF';
        file_put_contents($root.'/pdf/PP-81-2012.pdf', $pp);
        file_put_contents($root.'/pdf/UU-18-2008.pdf', $uu);
        $expected = [
            'PP-81-2012' => [
                'pdf/PP-81-2012.pdf',
                '542CE23457FE19529887D3B0BD4B14FAAE7F6F1E269CBFC5B840E117B2CDEE53',
                'https://www.peraturan.go.id/files/pp81-2012bt.pdf',
                strlen($pp),
            ],
            'UU-18-2008' => [
                'pdf/UU-18-2008.pdf',
                'DC7735DB65D6DEDC650FF78C36B6C6F076CF931861B005EDAB03F30E513026F4',
                'https://peraturan.bpk.go.id/Download/28462/UU%20Nomor%2018%20Tahun%202008.pdf',
                strlen($uu),
            ],
        ];
        $manifest = [
            'schema' => 'PI_R81_NATIONAL_PDF_OBSERVED_SNAPSHOTS_V1',
            'tenant_id' => 'ID-DIY-YOG', 'source_count' => 2,
            'source_identity_certification' => 'NOT_PERFORMED',
            'source_passage_verification' => 'NOT_PERFORMED',
            'r80_relation_promotion' => 'NOT_PERFORMED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'production_admission' => 'DENIED',
            'sources' => [],
        ];
        foreach ($expected as $id => $row) {
            $manifest['sources'][] = [
                'instrument_id' => $id, 'relative_path' => $row[0],
                'sha256_observed' => $row[1],
                'acquisition_mode' => 'OFFICIAL_HTTPS_NO_REDIRECT',
                'acquisition_url' => $row[2],
                'source_identity' => 'REVIEW_REQUIRED',
                'primary_passage_evidence' => 'NOT_VERIFIED',
                'legal_harmonization' => 'NOT_ASSESSED',
                'bytes' => $row[3],
            ];
        }
        file_put_contents($root.'/observed-manifest.json', json_encode($manifest));
        try {
            app(R82NationalSourceVerifier::class)->verify($root);
            $this->fail('Synthetic PDF must fail independent real SHA pin');
        } catch (RuntimeException $error) {
            $this->assertSame('R82_SOURCE_HASH_DRIFT', $error->getMessage());
        }

        $manifest['sources'][0]['sha256_observed'] = str_repeat('F', 64);
        file_put_contents($root.'/observed-manifest.json', json_encode($manifest));
        try {
            app(R82NationalSourceVerifier::class)->verify($root);
            $this->fail('Tampered manifest must fail');
        } catch (RuntimeException $error) {
            $this->assertSame('R82_SOURCE_PROVENANCE_REJECTED', $error->getMessage());
        }
    }

    public function test_r82_review_api_does_not_expose_unprepared_source_or_cross_tenant_data(): void
    {
        Http::preventStrayRequests();
        $url = '/api/qa-regulatory-r82/passage-candidates';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson($url)->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN')
            ->assertJsonPath('production_admission', 'DENIED');
        Http::assertNothingSent();
    }
}
