<?php

namespace Tests\Feature;

use App\Services\Pi\Development\PrimaryPdfCandidateIntake;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class R102PrimaryPdfCandidateIntakeTest extends TestCase
{
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $fileOrFolder) {
            if (is_dir($fileOrFolder)) {
                foreach (glob($fileOrFolder.'/*/*') ?: [] as $leaf) {
                    @unlink($leaf);
                }
                foreach (glob($fileOrFolder.'/*') ?: [] as $child) {
                    if (is_dir($child)) {
                        @rmdir($child);
                    } else {
                        @unlink($child);
                    }
                }
                @rmdir($fileOrFolder);
            } else {
                @unlink($fileOrFolder);
            }
        }
        parent::tearDown();
    }

    private function intake(): PrimaryPdfCandidateIntake
    {
        return new PrimaryPdfCandidateIntake(
            resource_path('pi/official-source-metadata-r100.json')
        );
    }

    private function candidatePdf(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pi-r102-pdf-');
        $this->assertIsString($path);
        $this->temporary[] = $path;

        // Deliberately SYNTHETIC PDF test fixture: structural gates only.
        // This is NOT any official document and cannot be an admitted source.
        file_put_contents($path, "%PDF-1.7\n".
            "1 0 obj\n<< /Type /Catalog >>\nendobj\n".
            str_repeat("synthetic-only-test-bytes\n", 12).
            "trailer\n<< /Root 1 0 R >>\nstartxref\n12\n%%EOF\n");

        return $path;
    }

    private function directory(): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pi-r102-q-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($path, 0700));
        $this->temporary[] = $path;
        return $path;
    }

    public function test_offline_read_only_pdf_hash_preview_does_not_create_archive(): void
    {
        Http::preventStrayRequests();
        $file = $this->candidatePdf();
        $quarantine = $this->directory();

        $result = $this->intake()->stage(
            'YOG-APBD-DETAIL-2026',
            $file,
            'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf',
            '2026-11-23T21:00:00+07:00',
            $quarantine
        );

        $receipt = $result['receipt'];
        $this->assertSame('PI_R102_PRIMARY_PDF_BYTE_CANDIDATE_RECEIPT_V1', $receipt['schema']);
        $this->assertSame('YOG-APBD-DETAIL-2026', $receipt['source_id']);
        $this->assertSame('ISOLATED_BYTE_CANDIDATE_ONLY', $receipt['status']);
        $this->assertSame('LOCAL_PDF_SIGNATURE_TAIL_AND_SHA256_PASS', $receipt['byte_integrity_gate']);
        $this->assertSame('NOT_AUTHENTICATED', $receipt['pdf_signatures_and_legality']);
        $this->assertSame('REVIEW_REQUIRED', $receipt['document_identity_match']);
        $this->assertSame('REVIEW_REQUIRED', $receipt['source_legal_rights']);
        $this->assertSame('2026-11-23T14:00:00Z', $receipt['captured_at_utc']);
        $this->assertFalse($receipt['source_file_url_retrieval_confirmed']);
        $this->assertSame(strtoupper(hash_file('sha256', $file)), $receipt['byte_sha256']);
        $this->assertSame(filesize($file), $receipt['byte_length']);
        $this->assertSame(0, $receipt['financial_amounts_admitted']);
        $this->assertSame('DENIED', $receipt['production_admission']);
        $this->assertNull($result['pdf_path']);
        $this->assertNull($result['receipt_path']);
        $this->assertEmpty(glob($quarantine.'/*'));
        Http::assertNothingSent();
    }

    public function test_explicit_archive_is_immutable_hash_pinned_and_keeps_human_review_gates(): void
    {
        Http::preventStrayRequests();
        $file = $this->candidatePdf();
        $quarantine = $this->directory();
        $uri = 'https://jdih.jogjakota.go.id/asset/naskah/2026pw3405012.pdf';

        $staged = $this->intake()->stage(
            'YOG-APBD-DETAIL-CHANGE3-2026',
            $file, $uri, '2026-11-23T21:00:00+07:00', $quarantine, true
        );

        $this->assertFileExists($staged['pdf_path']);
        $this->assertFileExists($staged['receipt_path']);
        $this->assertSame(
            hash_file('sha256', $file),
            hash_file('sha256', $staged['pdf_path'])
        );
        $receipt = json_decode(
            (string) file_get_contents($staged['receipt_path']), true, 64, JSON_THROW_ON_ERROR
        );
        $this->assertSame('NOT_REQUESTED', $receipt['reviewer_approval']);
        $this->assertSame('NOT_ASSESSED', $receipt['2026_to_2027_indicator_equivalence']);
        $this->assertSame('USER_SUPPLIED_LOCAL_FILE_OFFLINE', $receipt['captured_via']);
        $this->assertSame($uri, $receipt['source_file_url_claimed']);
        $this->assertFalse($receipt['source_file_url_retrieval_confirmed']);
        $this->assertSame('DENIED', $receipt['production_admission']);

        try {
            $this->intake()->stage(
                'YOG-APBD-DETAIL-CHANGE3-2026', $file, $uri,
                '2026-11-23T21:00:00+07:00', $quarantine, true
            );
            $this->fail('Immutable duplicate should be rejected.');
        } catch (RuntimeException $expected) {
            $this->assertSame('PI_R102_IMMUTABLE_SOURCE_ALREADY_STAGED_HOLD', $expected->getMessage());
        }
        $this->assertFileExists($staged['pdf_path']);
        Http::assertNothingSent();
    }

    public function test_maintenance_html_is_rejected_before_any_archive_or_claim(): void
    {
        $file = $this->candidatePdf();
        file_put_contents($file, '<html>Sedang proses maintenance</html>'.str_repeat(' ', 170));
        $quarantine = $this->directory();

        try {
            $this->intake()->stage(
                'YOG-APBD-DETAIL-2026', $file,
                'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf',
                '2026-11-23T21:00:00+07:00', $quarantine, true
            );
            $this->fail('Maintenance HTML must never pass as a PDF.');
        } catch (RuntimeException $expected) {
            $this->assertSame('PI_R102_NON_PDF_OR_MAINTENANCE_HTML_REJECTED', $expected->getMessage());
        }
        $this->assertEmpty(glob($quarantine.'/*'));
    }

    public function test_unreviewed_ids_unsafe_urls_and_missing_eof_are_rejected(): void
    {
        $file = $this->candidatePdf();
        $quarantine = $this->directory();
        $safe = 'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf';

        foreach ([
            ['NOT_IN_CATALOGUE', $safe, 'PI_R102_SOURCE_ID_NOT_IN_REVIEWED_INDEX'],
            ['YOG-APBD-DETAIL-2026', 'https://attacker.example/source.pdf', 'PI_R102_UNTRUSTED_SOURCE_URL_HOLD'],
            ['YOG-APBD-DETAIL-2026', 'https://jdih.jogjakota.go.id.evil.org/asset/naskah/file.pdf', 'PI_R102_UNTRUSTED_SOURCE_URL_HOLD'],
            ['YOG-APBD-DETAIL-2026', 'https://jdih.jogjakota.go.id/asset/naskah/file.pdf?redirect=1', 'PI_R102_UNTRUSTED_SOURCE_URL_HOLD'],
            ['YOG-APBD-DETAIL-2026', 'https://jdih.jogjakota.go.id@evil.test/asset/naskah/file.pdf', 'PI_R102_UNTRUSTED_SOURCE_URL_HOLD'],
        ] as [$id, $url, $error]) {
            try {
                $this->intake()->stage($id, $file, $url, '2026-11-23T21:00:00+07:00', $quarantine);
                $this->fail('Bad source was accepted: '.$url);
            } catch (RuntimeException $expected) {
                $this->assertSame($error, $expected->getMessage());
            }
        }

        file_put_contents($file, "%PDF-1.7\n".str_repeat('x', 300));
        try {
            $this->intake()->stage('YOG-APBD-DETAIL-2026', $file, $safe,
                '2026-11-23T21:00:00+07:00', $quarantine);
            $this->fail('Missing EOF should be rejected.');
        } catch (RuntimeException $expected) {
            $this->assertSame('PI_R102_PDF_EOF_MARKER_MISSING', $expected->getMessage());
        }
        $this->assertEmpty(glob($quarantine.'/*'));
    }

    public function test_pdf_signature_does_not_prove_authenticity_or_amounts(): void
    {
        $file = $this->candidatePdf();
        $quarantine = $this->directory();
        $result = $this->intake()->stage(
            'YOG-APBD-DETAIL-2026', $file,
            'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf',
            '2026-11-23T21:00:00+07:00', $quarantine
        );
        $receipt = $result['receipt'];
        $this->assertSame('NOT_AUTHENTICATED', $receipt['pdf_signatures_and_legality']);
        $this->assertSame('REVIEW_REQUIRED', $receipt['document_version_and_annexes']);
        $this->assertSame(0, $receipt['financial_amounts_admitted']);
        $this->assertSame('NOT_REQUESTED', $receipt['reviewer_approval']);
        $this->assertSame('DENIED', $receipt['production_admission']);
    }
}
