<?php
namespace App\Services\Pi\Development;
use RuntimeException;

/** R105 local SHA/receipt integrity pre-review. NO writes, DB, HTTP, AI, approval. */
final class BpkQuarantineReadOnlyReview
{
    private const SOURCES = [
        'YOG-APBD-DETAIL-2026' => [
            'url' => 'https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf',
            'detail' => 'https://peraturan.bpk.go.id/Details/346297/perwali-kota-yogyakarta-no-74-tahun-2025',
        ],
        'YOG-APBD-DETAIL-CHANGE3-2026' => [
            'url' => 'https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf',
            'detail' => 'https://peraturan.bpk.go.id/Details/350352/perwali-kota-yogyakarta-no-12-tahun-2026',
        ],
    ];
    public function __construct(
        private readonly string $manifestPath,
        private readonly string $cataloguePath,
        private readonly string $fiscalPath
    ) {}

    public function inspect(string $quarantineRoot): array
    {
        $root = realpath($quarantineRoot);
        if ($root === false || !is_dir($root) || is_link($quarantineRoot)) {
            throw new RuntimeException('PI_R105_ISOLATED_QUARANTINE_REQUIRED');
        }
        $pins = $this->json($this->manifestPath);
        if (($pins['schema'] ?? null) !== 'PI_R105_OWNER_BPK_BYTE_PINS_V1'
            || ($pins['byte_pin_origin'] ?? null) !== 'OWNER_R104_LOG_NOT_REMOTE_BINARY_VERIFICATION'
            || ($pins['document_identity'] ?? null) !== 'REVIEW_REQUIRED'
            || ($pins['numeric_admission'] ?? null) !== 0
            || !is_array($pins['entries'] ?? null) || count($pins['entries']) !== 2) {
            throw new RuntimeException('PI_R105_OWNER_PINS_CONTRACT_HOLD');
        }
        $catalogueHash = hash_file('sha256', $this->cataloguePath);
        if (!is_string($catalogueHash)) {
            throw new RuntimeException('PI_R105_R100_REGISTRY_SHA_UNAVAILABLE');
        }
        $seen = [];
        $verified = [];
        foreach ($pins['entries'] as $entry) {
            $id = $entry['source_id'] ?? null;
            if (!is_string($id) || !isset(self::SOURCES[$id]) || isset($seen[$id])
                || ($entry['bpk_pdf_url'] ?? null) !== self::SOURCES[$id]['url']
                || ($entry['bpk_detail_url'] ?? null) !== self::SOURCES[$id]['detail']) {
                throw new RuntimeException('PI_R105_SOURCE_ID_OR_BPK_URL_MISMATCH');
            }
            $seen[$id] = true;
            $sha = $entry['owner_sha256'] ?? null;
            $size = $entry['owner_bytes'] ?? null;
            if (!is_string($sha) || preg_match('/\A[A-F0-9]{64}\z/D', $sha) !== 1
                || !is_int($size) || $size < 128 || $size > 80*1024*1024) {
                throw new RuntimeException('PI_R105_OWNER_SIZE_OR_SHA_INVALID');
            }
            $dir = $root.DIRECTORY_SEPARATOR.$id;
            $lower = strtolower($sha);
            $pdf = $dir.DIRECTORY_SEPARATOR.$lower.'.pdf';
            $receiptPath = $dir.DIRECTORY_SEPARATOR.$lower.'.receipt.json';
            if (!is_dir($dir) || is_link($dir) || is_link($pdf) || is_link($receiptPath)
                || !is_file($pdf) || !is_file($receiptPath)) {
                throw new RuntimeException('PI_R105_ARCHIVE_PAIR_MISSING_OR_LINK');
            }
            $names = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
            sort($names);
            $expectedNames = [$lower.'.pdf', $lower.'.receipt.json'];
            sort($expectedNames);
            if ($names !== $expectedNames) {
                throw new RuntimeException('PI_R105_AMBIGUOUS_ARCHIVE_HOLD');
            }
            clearstatcache(true, $pdf);
            if (filesize($pdf) !== $size || strtoupper((string)hash_file('sha256', $pdf)) !== $sha) {
                throw new RuntimeException('PI_R105_ARCHIVE_SHA_OR_BYTES_MISMATCH');
            }
            $fh = fopen($pdf, 'rb');
            if ($fh === false) { throw new RuntimeException('PI_R105_PDF_UNREADABLE'); }
            try {
                $magic = fread($fh, 8);
                fseek($fh, -min($size, 4096), SEEK_END);
                $tail = stream_get_contents($fh);
                if (!is_string($magic) || (!str_starts_with($magic, '%PDF-1.')
                    && !str_starts_with($magic, '%PDF-2.'))
                    || !is_string($tail) || !str_contains($tail, '%%EOF')) {
                    throw new RuntimeException('PI_R105_PDF_HEADER_EOF_HOLD');
                }
            } finally { fclose($fh); }
            $receipt = $this->json($receiptPath);
            if (($receipt['schema'] ?? null) !== 'PI_R102_PRIMARY_PDF_BYTE_CANDIDATE_RECEIPT_V1'
                || ($receipt['source_id'] ?? null) !== $id
                || ($receipt['source_repository'] ?? null) !== 'BPK_OFFICIAL_REPUBLICATION'
                || ($receipt['source_repository_detail_url'] ?? null) !== self::SOURCES[$id]['detail']
                || ($receipt['source_file_url_claimed'] ?? null) !== self::SOURCES[$id]['url']
                || ($receipt['source_metadata_registry_sha256'] ?? null) !== strtoupper($catalogueHash)
                || ($receipt['source_file_url_retrieval_confirmed'] ?? null) !== false
                || ($receipt['source_bytes_direct_retrieval_proof'] ?? null) !== 'NOT_PRESENT_OFFLINE'
                || ($receipt['byte_sha256'] ?? null) !== $sha
                || ($receipt['byte_length'] ?? null) !== $size
                || ($receipt['byte_integrity_gate'] ?? null) !== 'LOCAL_PDF_SIGNATURE_TAIL_AND_SHA256_PASS'
                || ($receipt['status'] ?? null) !== 'ISOLATED_BYTE_CANDIDATE_ONLY'
                || ($receipt['document_identity_match'] ?? null) !== 'REVIEW_REQUIRED'
                || ($receipt['document_version_and_annexes'] ?? null) !== 'REVIEW_REQUIRED'
                || ($receipt['source_legal_rights'] ?? null) !== 'REVIEW_REQUIRED'
                || ($receipt['pdf_signatures_and_legality'] ?? null) !== 'NOT_AUTHENTICATED'
                || ($receipt['reviewer_approval'] ?? null) !== 'NOT_REQUESTED'
                || ($receipt['financial_amounts_admitted'] ?? null) !== 0
                || ($receipt['production_admission'] ?? null) !== 'DENIED') {
                throw new RuntimeException('PI_R105_RECEIPT_IDENTITY_OR_GATE_MISMATCH');
            }
            $verified[$id] = ['bytes' => $size, 'sha256' => $sha];
        }
        if (count($seen) !== 2) { throw new RuntimeException('PI_R105_PAIR_INCOMPLETE'); }
        $fiscal = $this->json($this->fiscalPath);
        if (($fiscal['schema'] ?? null) !== 'PI_R103_BPK_PDF_VIEW_FISCAL_CANDIDATE_V1'
            || ($fiscal['source_id'] ?? null) !== 'YOG-APBD-DETAIL-CHANGE3-2026'
            || ($fiscal['subactivity_code'] ?? null) !== '2.11.11.2.01.0004'
            || ($fiscal['source_pdf_page_one_based'] ?? null) !== 561
            || ($fiscal['printed_appendix_page'] ?? null) !== 520
            || !array_key_exists('source_pdf_sha256', $fiscal) || $fiscal['source_pdf_sha256'] !== null
            || ($fiscal['source_pdf_full_bytes_acquired'] ?? null) !== false
            || ($fiscal['interpretation']['value_admitted_to_fiscal_engine'] ?? null) !== false
            || ($fiscal['interpretation']['reviewer_approved'] ?? null) !== false
            || ($fiscal['interpretation']['production_admission'] ?? null) !== 'DENIED'
            || !is_array($fiscal['rows'] ?? null) || count($fiscal['rows']) !== 6) {
            throw new RuntimeException('PI_R105_UNREVIEWED_FISCAL_CONTRACT_HOLD');
        }
        $before = 0; $after = 0;
        foreach ($fiscal['rows'] as $line) {
            if (!is_int($line['before_idr'] ?? null) || !is_int($line['after_idr'] ?? null)
                || !is_int($line['delta_idr'] ?? null) || $line['before_idr'] < 0
                || $line['after_idr'] < 0
                || $line['delta_idr'] !== $line['after_idr'] - $line['before_idr']
                || ($line['source_pdf_page_index_zero_based'] ?? null) !== 560) {
                throw new RuntimeException('PI_R105_FISCAL_ROW_ARITHMETIC_HOLD');
            }
            $before += $line['before_idr']; $after += $line['after_idr'];
        }
        if ($before !== 1020366250 || $after !== 1020366250
            || ($fiscal['derived_totals']['before_idr'] ?? null) !== $before
            || ($fiscal['derived_totals']['after_idr'] ?? null) !== $after
            || ($fiscal['derived_totals']['delta_idr'] ?? null) !== 0) {
            throw new RuntimeException('PI_R105_DERIVED_TOTAL_NOT_MATCHED');
        }
        return [
            'sources' => $verified, 'six_visible_rows_derived_idr' => $after,
            'local_byte_receipt_integrity' => 'PASS',
            'legal_identity_origin_annex_and_account_codes' => 'REVIEW_REQUIRED',
            'amendments_13_and_15_2026' => 'NOT_RECONCILED',
            'rka_dpa_realization' => 'NOT_VERIFIED',
            '2027_equivalence' => 'ABSTAIN',
            'numeric_admission' => 0, 'reviewer_approval' => 'NOT_REQUESTED',
            'production_admission' => 'DENIED', 'writes_performed' => false,
        ];
    }

    private function json(string $path): array
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)
            || filesize($path) === false || filesize($path) > 262144) {
            throw new RuntimeException('PI_R105_INPUT_UNSAFE_OR_MISSING');
        }
        try { $value = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('PI_R105_INVALID_JSON_HOLD'); }
        if (!is_array($value)) { throw new RuntimeException('PI_R105_JSON_NOT_OBJECT_HOLD'); }
        return $value;
    }
}
