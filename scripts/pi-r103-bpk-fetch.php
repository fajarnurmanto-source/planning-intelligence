<?php
declare(strict_types=1);

/**
 * PI R103 — direct HTTPS acquisition of TWO pinned official BPK PDF mirrors.
 * No Laravel boot or database. Default is read-only: explicit --fetch required.
 *
 * php scripts/pi-r103-bpk-fetch.php --source=YOG-APBD-DETAIL-2026
 *    --output-dir=/isolated/primary-candidates [--fetch]
 *
 * This fetcher archives a byte candidate and an immutable HTTP acquisition
 * receipt. It does NOT verify the originating city JDIH file, signatures,
 * legal scope, appendix content, rights, or fiscal figures.
 */
$allow = [
    'YOG-APBD-DETAIL-2026' => [
        'url' => 'https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf',
        'detail' => 'https://peraturan.bpk.go.id/Details/346297/perwali-kota-yogyakarta-no-74-tahun-2025',
    ],
    'YOG-APBD-DETAIL-CHANGE3-2026' => [
        'url' => 'https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf',
        'detail' => 'https://peraturan.bpk.go.id/Details/350352/perwali-kota-yogyakarta-no-12-tahun-2026',
    ],
];
$params = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--fetch' && ! isset($params['fetch'])) {
        $params['fetch'] = true;
        continue;
    }
    if (! str_starts_with($arg, '--') || ! str_contains($arg, '=')) {
        fwrite(STDERR, "PI_R103_UNKNOWN_PARAMETER_HOLD\n");
        exit(2);
    }
    [$name, $value] = explode('=', substr($arg, 2), 2);
    if (! in_array($name, ['source', 'output-dir'], true)
        || $value === '' || isset($params[$name])) {
        fwrite(STDERR, "PI_R103_INVALID_PARAMETER_HOLD\n");
        exit(2);
    }
    $params[$name] = $value;
}
$id = $params['source'] ?? '';
if (! is_string($id) || ! isset($allow[$id])) {
    fwrite(STDERR, "PI_R103_SOURCE_NOT_IN_EXACT_TWO_RECORD_ALLOWLIST\n");
    exit(2);
}
$url = $allow[$id]['url'];
$detail = $allow[$id]['detail'];
$cataloguePath = dirname(__DIR__).'/laravel/resources/pi/official-bpk-pdf-mirrors-r103.json';
if (! is_file($cataloguePath) || is_link($cataloguePath)
    || filesize($cataloguePath) > 48000) {
    fwrite(STDERR, "PI_R103_PINNED_BPK_CATALOGUE_UNAVAILABLE\n");
    exit(2);
}
try {
    $catalogue = json_decode((string) file_get_contents($cataloguePath), true, 64, JSON_THROW_ON_ERROR);
    if (($catalogue['schema'] ?? '') !== 'PI_R103_BPK_OFFICIAL_REPUBLICATION_ALLOWLIST_V1'
        || count($catalogue['entries'] ?? []) !== 2
        || ($catalogue['production_admission'] ?? '') !== 'DENIED') {
        throw new RuntimeException('PI_R103_PINNED_BPK_CATALOGUE_INVALID');
    }
    $identities = [];
    foreach ($catalogue['entries'] as $entry) {
        if (! is_array($entry)
            || ! isset($allow[$entry['source_id'] ?? ''])
            || isset($identities[$entry['source_id']])
            || $entry['bpk_pdf_url'] !== $allow[$entry['source_id']]['url']
            || $entry['bpk_detail_url'] !== $allow[$entry['source_id']]['detail']
            || ! array_key_exists('file_bytes_sha256', $entry)
            || $entry['file_bytes_sha256'] !== null) {
            throw new RuntimeException('PI_R103_BPK_RECORD_MISMATCH_HOLD');
        }
        $identities[$entry['source_id']] = true;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "PI_R103_BPK_CATALOGUE_HOLD\n");
    exit(2);
}
echo "PI_R103_SOURCE_ID={$id}\n";
echo "PI_R103_BPK_DETAIL_URL={$detail}\n";
echo "PI_R103_BPK_DOWNLOAD_URL={$url}\n";
echo "PI_R103_ORIGIN_JDIH_BYTES=NOT_VERIFIED\n";
echo "PI_R103_REVIEWER_APPROVAL=NOT_REQUESTED\n";
echo "PI_R103_FISCAL_AMOUNTS_ADMITTED=0\n";
echo "PI_R103_PRODUCTION_ADMISSION=DENIED\n";

if (! ($params['fetch'] ?? false)) {
    echo "PI_R103_DIRECT_BPK_FETCH=NOT_RUN_DEFAULT_READ_ONLY\n";
    exit(0);
}
if (! function_exists('curl_init')) {
    fwrite(STDERR, "PI_R103_PHP_CURL_REQUIRED\n");
    exit(2);
}
$directory = $params['output-dir'] ?? '';
if (! is_string($directory) || is_link($directory)
    || ! is_dir($directory) || ! is_writable($directory)) {
    fwrite(STDERR, "PI_R103_ISOLATED_OUTPUT_DIR_REQUIRED\n");
    exit(2);
}
$targetDir = realpath($directory);
$repo = realpath(dirname(__DIR__));
$normalize = static fn (string $s): string => strtolower(str_replace('\\', '/', rtrim($s, DIRECTORY_SEPARATOR)));
$dirNorm = $normalize((string) $targetDir);
foreach (array_filter([
    is_string($repo) ? $normalize($repo) : null,
    'd:/research/planningintelligence/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel',
]) as $forbidden) {
    if ($dirNorm === $forbidden || str_starts_with($dirNorm, $forbidden.'/')) {
        fwrite(STDERR, "PI_R103_WRITING_INSIDE_PROTECTED_CODE_FORBIDDEN\n");
        exit(2);
    }
}
if (str_contains($dirNorm, '/public/') || str_ends_with($dirNorm, '/public')) {
    fwrite(STDERR, "PI_R103_QUARANTINE_MUST_NOT_BE_WEB_PUBLIC\n");
    exit(2);
}

$part = tempnam($targetDir, '.pi-r103-part-');
if (! is_string($part)) {
    fwrite(STDERR, "PI_R103_TEMP_CREATE_FAILED\n");
    exit(2);
}
$stream = fopen($part, 'wb');
if ($stream === false) {
    @unlink($part);
    fwrite(STDERR, "PI_R103_TEMP_NOT_WRITABLE\n");
    exit(2);
}

$maxBytes = 80 * 1024 * 1024;
$written = 0;
$tooLarge = false;
$curl = curl_init($url);
if ($curl === false) {
    fclose($stream);
    @unlink($part);
    fwrite(STDERR, "PI_R103_CURL_INIT_FAILED\n");
    exit(2);
}

try {
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT => 140,
        CURLOPT_CONNECTTIMEOUT => 18,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'PlanningIntelligence-evidence-candidate/1.0 (official public PDF archive)',
        CURLOPT_HTTPHEADER => ['Accept: application/pdf', 'Cache-Control: no-cache'],
        CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (
            $stream, $maxBytes, &$written, &$tooLarge
        ): int {
            $n = strlen($chunk);
            if ($written + $n > $maxBytes) {
                $tooLarge = true;
                return 0;
            }
            $wrote = fwrite($stream, $chunk);
            if ($wrote !== $n) {
                return 0;
            }
            $written += $wrote;
            return $wrote;
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $finalUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($curl);
} finally {
    curl_close($curl);
    fclose($stream);
}
try {
    if ($ok !== true || $status !== 200 || $finalUrl !== $url || $tooLarge) {
        throw new RuntimeException('PI_R103_HTTPS_FETCH_HOLD_STATUS_'.$status.($tooLarge ? '_MAX_BYTES' : ''));
    }
    if ($written < 128) {
        throw new RuntimeException('PI_R103_DOWNLOAD_NOT_PDF_TOO_SMALL');
    }
    $h = fopen($part, 'rb');
    if ($h === false) {
        throw new RuntimeException('PI_R103_FILE_OPEN_FAILED');
    }
    try {
        $magic = fread($h, 8);
        if (! is_string($magic) || (! str_starts_with($magic, '%PDF-1.')
            && ! str_starts_with($magic, '%PDF-2.'))) {
            throw new RuntimeException('PI_R103_HTML_OR_NON_PDF_MIRROR_REJECTED');
        }
        if (fseek($h, -min(4096, $written), SEEK_END) !== 0
            || ! str_contains((string) stream_get_contents($h), '%%EOF')) {
            throw new RuntimeException('PI_R103_PDF_FOOTER_MISSING');
        }
    } finally {
        fclose($h);
    }
    $digest = hash_file('sha256', $part);
    if (! is_string($digest)) {
        throw new RuntimeException('PI_R103_SHA256_UNAVAILABLE');
    }
    $dest = $targetDir.DIRECTORY_SEPARATOR.$id.'--'.$digest.'.pdf';
    $receiptPath = $targetDir.DIRECTORY_SEPARATOR.$id.'--'.$digest.'.download.json';
    if (file_exists($dest) || file_exists($receiptPath)) {
        throw new RuntimeException('PI_R103_IMMUTABLE_DUPLICATE_HOLD');
    }
    if (! rename($part, $dest)) {
        throw new RuntimeException('PI_R103_ARCHIVE_RENAME_FAILED');
    }
    $receipt = [
        'schema' => 'PI_R103_BPK_HTTP_BYTES_RECEIPT_V1',
        'source_id' => $id,
        'source_repository' => 'BPK_OFFICIAL_REPUBLICATION',
        'source_pdf_url' => $url,
        'bpk_detail_url' => $detail,
        'retrieved_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'http_status' => $status,
        'http_content_type_observed' => $type,
        'bytes' => $written,
        'sha256' => strtoupper($digest),
        'pdf_header_tail_structural_check' => 'PASS',
        'source_url_retrieval_confirmed' => true,
        'primary_city_jdih_byte_equivalence' => 'NOT_VERIFIED',
        'legal_signature_rights_appendices' => 'REVIEW_REQUIRED',
        'document_identity_review' => 'REVIEW_REQUIRED',
        'financial_numbers_admitted' => 0,
        'production_admission' => 'DENIED',
    ];
    $out = fopen($receiptPath, 'x');
    if ($out === false) {
        @unlink($dest);
        throw new RuntimeException('PI_R103_RECEIPT_COLLISION');
    }
    $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    try {
        if (fwrite($out, $json) !== strlen($json)) {
            throw new RuntimeException('PI_R103_RECEIPT_INCOMPLETE');
        }
    } catch (Throwable $error) {
        fclose($out);
        @unlink($dest);
        @unlink($receiptPath);
        throw $error;
    }
    fclose($out);
    echo "PI_R103_BPK_HTTP_200_PDF_BYTES={$written}\n";
    echo "PI_R103_BPK_SHA256=".strtoupper($digest)."\n";
    echo "PI_R103_ARCHIVED_CANDIDATE={$dest}\n";
    echo "PI_R103_RECEIPT={$receiptPath}\n";
    echo "PI_R103_BPK_BYTES_CANDIDATE_ARCHIVED=PASS\n";
} catch (Throwable $error) {
    if (is_file($part)) {
        @unlink($part);
    }
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
