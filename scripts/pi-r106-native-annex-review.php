<?php
declare(strict_types=1);
/** R106 operator QA CLI. No web, DB, file writes, automatic approval. */
$base = dirname(__DIR__);
require_once $base.'/laravel/app/Services/Pi/Development/BpkQuarantineReadOnlyReview.php';
require_once $base.'/laravel/app/Services/Pi/Development/NativePdfAnnexIntelligence.php';
use App\Services\Pi\Development\BpkQuarantineReadOnlyReview;
use App\Services\Pi\Development\NativePdfAnnexIntelligence;
$args = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(root|pdftotext)=(.+)$/D', $argument, $m)
        || isset($args[$m[1]])) {
        fwrite(STDERR, "PI_R106_UNKNOWN_PARAMETER_HOLD\n"); exit(2);
    }
    $args[$m[1]] = $m[2];
}
$local = getenv('LOCALAPPDATA');
$root = $args['root'] ?? (is_string($local) && $local !== ''
    ? $local.DIRECTORY_SEPARATOR.'PlanningIntelligence'.DIRECTORY_SEPARATOR.'R104_BPK_PDF_QUARANTINE' : '');
if ($root === '') { fwrite(STDERR, "PI_R106_QUARANTINE_ROOT_REQUIRED\n"); exit(2); }
try {
    $r105 = (new BpkQuarantineReadOnlyReview(
        $base.'/laravel/resources/pi/r105-owner-bpk-byte-pins.json',
        $base.'/laravel/resources/pi/official-source-metadata-r100.json',
        $base.'/laravel/resources/pi/bpk-perwal12-dlh-0004-fiscal-candidate-r103.json'
    ))->inspect($root);
    if ($r105['local_byte_receipt_integrity'] !== 'PASS') {
        throw new RuntimeException('PI_R106_R105_GATE_BLOCKED');
    }
    $pins = [
        'base' => ['YOG-APBD-DETAIL-2026', 'e4aaa45f33b7254fdb7bbe3a0bc456999fd2ce3e19d8c07b874c640a5e1dee24'],
        'change3' => ['YOG-APBD-DETAIL-CHANGE3-2026', '4e5194e6987468e30406911b277bacca73506b3bf93be413c0c19784ae3825ea'],
    ];
    $files = [];
    foreach ($pins as $key => [$id, $sha]) {
        $files[$key] = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$sha.'.pdf';
    }
    $result = (new NativePdfAnnexIntelligence)->review(
        $files['base'], $files['change3'], $args['pdftotext'] ?? null);
    echo "PI_R106_R105_IMMUTABLE_PAIR_GUARD=PASS\n";
    echo 'PI_R106_BASE_PAGE='.$result['source_documents']['base']['source_pdf_page']."\n";
    echo 'PI_R106_CHANGE3_PAGE='.$result['source_documents']['third_amendment']['source_pdf_page']."\n";
    echo 'PI_R106_FULL_ACCOUNT_CODES='.$result['row_count']."\n";
    foreach ($result['rows'] as $line) {
        echo 'PI_R106_ACCOUNT='.$line['account_code'].'|'.$line['base_idr']
            .'|'.$line['third_amendment_after_idr']."\n";
    }
    echo 'PI_R106_DERIVED_SIX_LEAF_IDR='.$result['derived_leaf_total_idr']."\n";
    echo "PI_R106_NATIVE_TEXT_LAYER_RECONCILIATION=PASS_CANDIDATE_ONLY\n";
    echo "PI_R106_LATER_AMENDMENT_REVIEW=REQUIRED\n";
    echo "PI_R106_REVIEWER_APPROVAL=NOT_REQUESTED\n";
    echo "PI_R106_NUMERIC_ADMISSION=ZERO\n";
    echo "PI_R106_PRODUCTION_ADMISSION=DENIED\n";
    echo "PI_R106_NO_MUTATIONS=YES\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n"); exit(1);
}
