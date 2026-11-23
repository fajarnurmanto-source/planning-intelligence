<?php

namespace App\Services\Pi\Development;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

/**
 * R102: offline byte-level PDF candidate intake. No HTTP, model calls,
 * source interpretation, database writes or production evidence admission.
 * Only an explicitly requested --commit saves bytes to an OUTSIDE-LARAVEL
 * quarantine with immutable hash receipts.
 */
final class PrimaryPdfCandidateIntake
{
    private const MAX_BYTES = 80 * 1024 * 1024;
    private const MIN_BYTES = 128;
    private const SOURCE_PREFIX = 'https://jdih.jogjakota.go.id/asset/naskah/';

    public function __construct(private readonly string $cataloguePath) {}

    /**
     * @return array{receipt:array<string,mixed>,pdf_path:?string,receipt_path:?string}
     */
    public function stage(
        string $sourceId,
        string $pdfPath,
        string $officialPdfUrl,
        string $capturedAt,
        string $stagingRoot,
        bool $commit = false
    ): array {
        $identity = $this->sourceIdentity($sourceId);
        $repository = $this->resolveOfficialRepository($sourceId, $officialPdfUrl, $identity);

        try {
            $time = new DateTimeImmutable($capturedAt);
            if ($time->format('Y-m-d\TH:i:sP') !== $capturedAt) {
                throw new RuntimeException('PI_R102_CAPTURE_TIME_MUST_BE_ISO8601_SECONDS_OFFSET');
            }
        } catch (\Throwable) {
            throw new RuntimeException('PI_R102_CAPTURE_TIME_INVALID');
        }
        $timestamp = $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');

        if (is_link($pdfPath) || ! is_file($pdfPath) || ! is_readable($pdfPath)) {
            throw new RuntimeException('PI_R102_LOCAL_PDF_NOT_SAFE_OR_READABLE');
        }
        $bytes = filesize($pdfPath);
        if ($bytes === false || $bytes < self::MIN_BYTES || $bytes > self::MAX_BYTES) {
            throw new RuntimeException('PI_R102_LOCAL_PDF_SIZE_OUTSIDE_GATES');
        }
        $handle = fopen($pdfPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('PI_R102_LOCAL_PDF_CANNOT_OPEN');
        }
        try {
            $magic = fread($handle, 8);
            if (! is_string($magic) || (! str_starts_with($magic, '%PDF-1.')
                && ! str_starts_with($magic, '%PDF-2.'))) {
                throw new RuntimeException('PI_R102_NON_PDF_OR_MAINTENANCE_HTML_REJECTED');
            }
            if (fseek($handle, -min(4096, $bytes), SEEK_END) !== 0) {
                throw new RuntimeException('PI_R102_PDF_TAIL_UNAVAILABLE');
            }
            $tail = stream_get_contents($handle);
            if (! is_string($tail) || ! str_contains($tail, '%%EOF')) {
                throw new RuntimeException('PI_R102_PDF_EOF_MARKER_MISSING');
            }
        } finally {
            fclose($handle);
        }
        $digest = hash_file('sha256', $pdfPath);
        if (! is_string($digest) || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
            throw new RuntimeException('PI_R102_PDF_SHA256_UNAVAILABLE');
        }

        $registryDigest = hash_file('sha256', $this->cataloguePath);
        if (! is_string($registryDigest)) {
            throw new RuntimeException('PI_R102_INDEX_SHA256_UNAVAILABLE');
        }
        $receipt = [
            'schema' => 'PI_R102_PRIMARY_PDF_BYTE_CANDIDATE_RECEIPT_V1',
            'source_id' => $sourceId,
            'case_id' => 'YOG-2026-PERSAMPAHAN',
            'origin_metadata_url' => $identity['official_url'],
            'source_repository' => $repository['id'],
            'source_repository_detail_url' => $repository['detail_url'],
            'source_repository_relationship' => $repository['relationship'],
            'source_repository_url_allowlist' => 'EXACT_REVIEWED_RECORD_ONLY',
            'source_bytes_direct_retrieval_proof' => 'NOT_PRESENT_OFFLINE',

            'source_file_url_claimed' => $officialPdfUrl,
            'source_file_url_retrieval_confirmed' => false,
            'document_identity_match' => 'REVIEW_REQUIRED',
            'captured_at_utc' => $timestamp,
            'captured_via' => 'USER_SUPPLIED_LOCAL_FILE_OFFLINE',
            'source_metadata_registry_sha256' => strtoupper($registryDigest),
            'byte_sha256' => strtoupper($digest),
            'byte_length' => $bytes,
            'byte_integrity_gate' => 'LOCAL_PDF_SIGNATURE_TAIL_AND_SHA256_PASS',
            'pdf_signatures_and_legality' => 'NOT_AUTHENTICATED',
            'source_legal_rights' => 'REVIEW_REQUIRED',
            'document_version_and_annexes' => 'REVIEW_REQUIRED',
            'reviewer_approval' => 'NOT_REQUESTED',
            'financial_amounts_admitted' => 0,
            '2026_to_2027_indicator_equivalence' => 'NOT_ASSESSED',
            'production_admission' => 'DENIED',
            'status' => 'ISOLATED_BYTE_CANDIDATE_ONLY',
        ];

        if (! $commit) {
            return ['receipt' => $receipt, 'pdf_path' => null, 'receipt_path' => null];
        }

        // The script/host must supply a truly isolated staging root;
        // never auto-create a location inside protected original Laravel.
        if (! is_dir($stagingRoot) || is_link($stagingRoot) || ! is_writable($stagingRoot)) {
            throw new RuntimeException('PI_R102_STAGING_ROOT_NOT_ISOLATED_OR_WRITABLE');
        }
        $root = realpath($stagingRoot);
        $src = realpath($pdfPath);
        if (! is_string($root) || ! is_string($src)
            || str_starts_with($src, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('PI_R102_SOURCE_MUST_STAY_OUTSIDE_STAGING_ROOT');
        }

        $idDir = $root.DIRECTORY_SEPARATOR.$sourceId;
        if (! is_dir($idDir) && ! mkdir($idDir, 0700, false) && ! is_dir($idDir)) {
            throw new RuntimeException('PI_R102_STAGING_SUBDIR_CREATE_FAILED');
        }
        if (is_link($idDir)) {
            throw new RuntimeException('PI_R102_STAGING_SUBDIR_SYMLINK_FORBIDDEN');
        }
        $dest = $idDir.DIRECTORY_SEPARATOR.$digest.'.pdf';
        $receiptPath = $idDir.DIRECTORY_SEPARATOR.$digest.'.receipt.json';
        if (file_exists($dest) || file_exists($receiptPath)) {
            throw new RuntimeException('PI_R102_IMMUTABLE_SOURCE_ALREADY_STAGED_HOLD');
        }
        $temp = tempnam($idDir, '.r102-part-');
        if (! is_string($temp)) {
            throw new RuntimeException('PI_R102_STAGING_TEMP_FAILED');
        }
        $moved = false;
        try {
            if (! copy($pdfPath, $temp)
                || hash_file('sha256', $temp) !== $digest
                || filesize($temp) !== $bytes) {
                throw new RuntimeException('PI_R102_COPY_HASH_MISMATCH');
            }
            if (file_exists($dest) || ! rename($temp, $dest)) {
                throw new RuntimeException('PI_R102_IMMUTABLE_FINAL_FILE_COLLISION');
            }
            $moved = true;
            $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            $out = fopen($receiptPath, 'x');
            if ($out === false) {
                throw new RuntimeException('PI_R102_IMMUTABLE_RECEIPT_COLLISION');
            }
            try {
                if (fwrite($out, $json) !== strlen($json)) {
                    throw new RuntimeException('PI_R102_RECEIPT_WRITE_INCOMPLETE');
                }
            } finally {
                fclose($out);
            }
            if (hash_file('sha256', $dest) !== $digest) {
                throw new RuntimeException('PI_R102_FINAL_BYTE_HASH_MISMATCH');
            }

            return ['receipt' => $receipt, 'pdf_path' => $dest, 'receipt_path' => $receiptPath];
        } catch (\Throwable $failure) {
            if ($moved && is_file($dest)) {
                @unlink($dest);
            }
            if (is_file($receiptPath)) {
                @unlink($receiptPath);
            }
            throw $failure;
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /** @return array<string,mixed> */
    private function sourceIdentity(string $sourceId): array
    {
        if (preg_match('/\AYOG-[A-Z0-9-]{3,100}\z/D', $sourceId) !== 1
            || ! is_file($this->cataloguePath) || is_link($this->cataloguePath)
            || ! is_readable($this->cataloguePath)) {
            throw new RuntimeException('PI_R102_SOURCE_INDEX_OR_ID_UNTRUSTED');
        }
        try {
            $registry = json_decode(
                (string) file_get_contents($this->cataloguePath),
                true,
                128,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('PI_R102_SOURCE_INDEX_MALFORMED', 0, $exception);
        }
        if (! is_array($registry)
            || ($registry['schema'] ?? null) !== 'PI_R100_OFFICIAL_SOURCE_METADATA_CATALOGUE_V1'
            || ($registry['production_admission'] ?? null) !== 'DENIED'
            || ($registry['document_bytes_verification'] ?? null) !== 'NOT_ACQUIRED_NOT_HASH_PINNED'
            || ! is_array($registry['documents'] ?? null)) {
            throw new RuntimeException('PI_R102_SOURCE_INDEX_NOT_FAIL_CLOSED');
        }
        foreach ($registry['documents'] as $document) {
            if (($document['id'] ?? null) === $sourceId
                && ($document['source_metadata_status'] ?? null) === 'OFFICIAL_JDIH_INDEXED'
                && ($document['document_bytes_status'] ?? null) === 'NOT_ACQUIRED_NOT_HASH_PINNED'
                && array_key_exists('document_sha256', $document)
                && $document['document_sha256'] === null
                && array_key_exists('numeric_amount', $document)
                && $document['numeric_amount'] === null) {
                return $document;
            }
        }
        throw new RuntimeException('PI_R102_SOURCE_ID_NOT_IN_REVIEWED_INDEX');
    }

    /**
     * @param array<string,mixed> $identity
     * @return array{id:string,detail_url:string,relationship:string}
     */
    private function resolveOfficialRepository(
        string $sourceId, string $url, array $identity
    ): array {
        $sourceMetadataUrl = (string) ($identity['official_url'] ?? '');
        if ($this->isOfficialPdfUrl($url)) {
            if ($sourceMetadataUrl !== $url
                && ! str_contains($sourceMetadataUrl, '/home/produk_hukum/detail/')) {
                throw new RuntimeException('PI_R102_OFFICIAL_URL_IDENTITY_MISMATCH');
            }

            return [
                'id' => 'JDIH_KOTA_YOGYAKARTA',
                'detail_url' => $sourceMetadataUrl,
                'relationship' => 'LOCAL_GOVERNMENT_ORIGIN_CLAIM_NOT_FETCH_VERIFIED',
            ];
        }

        // BPK is an OFFICIAL REPUBLICATION of local government legislation,
        // not proof of matching bytes/signatures from originating JDIH.
        // Enforce exact pinned document+URL pairs even if the mirror JSON is
        // accidentally edited. Do not accept arbitrary /Download/<id> paths.
        $canonical = [
            'YOG-APBD-DETAIL-2026' => [
                'pdf' => 'https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf',
                'detail' => 'https://peraturan.bpk.go.id/Details/346297/perwali-kota-yogyakarta-no-74-tahun-2025',
            ],
            'YOG-APBD-DETAIL-CHANGE3-2026' => [
                'pdf' => 'https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf',
                'detail' => 'https://peraturan.bpk.go.id/Details/350352/perwali-kota-yogyakarta-no-12-tahun-2026',
            ],
        ];
        if (! isset($canonical[$sourceId]) || $canonical[$sourceId]['pdf'] !== $url) {
            throw new RuntimeException('PI_R102_UNTRUSTED_SOURCE_URL_HOLD');
        }
        if (! str_starts_with($sourceMetadataUrl, 'https://jdih.jogjakota.go.id/')) {
            throw new RuntimeException('PI_R103_BPK_ORIGINAL_JDIH_IDENTITY_HOLD');
        }

        $index = dirname($this->cataloguePath).DIRECTORY_SEPARATOR.'official-bpk-pdf-mirrors-r103.json';
        if (is_link($index) || ! is_file($index) || ! is_readable($index)
            || filesize($index) > 48 * 1024) {
            throw new RuntimeException('PI_R103_BPK_MIRROR_INDEX_UNAVAILABLE');
        }
        try {
            $mirrors = json_decode((string) file_get_contents($index), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $failure) {
            throw new RuntimeException('PI_R103_BPK_MIRROR_INDEX_MALFORMED', 0, $failure);
        }
        if (! is_array($mirrors)
            || ($mirrors['schema'] ?? null) !== 'PI_R103_BPK_OFFICIAL_REPUBLICATION_ALLOWLIST_V1'
            || ($mirrors['bytes_acquired_into_quarantine'] ?? null) !== 0
            || ($mirrors['bytes_sha256_confirmed'] ?? null) !== 0
            || ($mirrors['production_admission'] ?? null) !== 'DENIED'
            || ! is_array($mirrors['entries'] ?? null)
            || count($mirrors['entries']) !== 2) {
            throw new RuntimeException('PI_R103_BPK_MIRROR_CONTRACT_HOLD');
        }
        $records = [];
        foreach ($mirrors['entries'] as $record) {
            if (! is_array($record) || ! is_string($record['source_id'] ?? null)
                || ! isset($canonical[$record['source_id']])
                || isset($records[$record['source_id']])
                || ($record['bpk_pdf_url'] ?? null) !== $canonical[$record['source_id']]['pdf']
                || ($record['bpk_detail_url'] ?? null) !== $canonical[$record['source_id']]['detail']
                || ($record['repository'] ?? null) !== 'BPK_OFFICIAL_REPUBLICATION'
                || ($record['primary_repository'] ?? null) !== 'JDIH_KOTA_YOGYAKARTA'
                || ! array_key_exists('file_bytes_sha256', $record)
                || $record['file_bytes_sha256'] !== null
                || ($record['official_pdf_document_identity'] ?? null) !== 'REVIEW_REQUIRED') {
                throw new RuntimeException('PI_R103_BPK_MIRROR_UNEXPECTED_ID_OR_URL_HOLD');
            }
            $records[$record['source_id']] = true;
        }
        if (count($records) !== count($canonical)) {
            throw new RuntimeException('PI_R103_BPK_MIRROR_PAIR_INCOMPLETE');
        }

        return [
            'id' => 'BPK_OFFICIAL_REPUBLICATION',
            'detail_url' => $canonical[$sourceId]['detail'],
            'relationship' => 'OFFICIAL_BPK_REPUBLICATION_OF_CITY_LEGAL_RECORD_NOT_ORIGIN_BYTES',
        ];
    }

    private function isOfficialPdfUrl(string $url): bool
    {
        if (! str_starts_with($url, self::SOURCE_PREFIX) || strlen($url) > 260) {
            return false;
        }
        $parsed = parse_url($url);
        return is_array($parsed)
            && ($parsed['scheme'] ?? null) === 'https'
            && ($parsed['host'] ?? null) === 'jdih.jogjakota.go.id'
            && ! isset($parsed['user']) && ! isset($parsed['pass']) && ! isset($parsed['port'])
            && ! isset($parsed['query']) && ! isset($parsed['fragment'])
            && preg_match('~\A/asset/naskah/[A-Za-z0-9._-]{4,100}\.pdf\z~D', (string) ($parsed['path'] ?? '')) === 1;
    }
}
