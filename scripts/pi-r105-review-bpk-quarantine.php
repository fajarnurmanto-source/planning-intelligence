<?php
declare(strict_types=1);
/** R105 offline read-only receipt verification; NEVER runs within owner PI. */
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/BpkQuarantineReadOnlyReview.php';
use App\Services\Pi\Development\BpkQuarantineReadOnlyReview;
$args = array_slice($argv, 1);
if (count($args) > 1 || ($args && !str_starts_with($args[0], '--root='))) {
    fwrite(STDERR, "PI_R105_UNKNOWN_PARAMETER_HOLD\n"); exit(2);
}
$local = getenv('LOCALAPPDATA');
$root = $args ? substr($args[0], 7)
    : (is_string($local) && $local !== '' ? $local.DIRECTORY_SEPARATOR.'PlanningIntelligence'.DIRECTORY_SEPARATOR.'R104_BPK_PDF_QUARANTINE' : '');
if ($root === '') { fwrite(STDERR, "PI_R105_QUARANTINE_ROOT_REQUIRED\n"); exit(2); }
$base = dirname(__DIR__);
try {
    $checker = new BpkQuarantineReadOnlyReview(
        $base.'/laravel/resources/pi/r105-owner-bpk-byte-pins.json',
        $base.'/laravel/resources/pi/official-source-metadata-r100.json',
        $base.'/laravel/resources/pi/bpk-perwal12-dlh-0004-fiscal-candidate-r103.json'
    );
    $result = $checker->inspect($root);
    foreach ($result['sources'] as $id => $item) {
        echo 'PI_R105_SOURCE='.$id."\n";
        echo 'PI_R105_BYTES='.$item['bytes']."\n";
        echo 'PI_R105_SHA256='.$item['sha256']."\n";
    }
    echo "PI_R105_TWO_PDF_SHA_AND_RECEIPT=PASS_LOCAL_ONLY\n";
    echo 'PI_R105_SIX_VISIBLE_ROWS_DERIVED_IDR='.$result['six_visible_rows_derived_idr']."\n";
    echo "PI_R105_LEGAL_IDENTITY_ORIGIN_ANNEX_ACCOUNT_CODES=REVIEW_REQUIRED\n";
    echo "PI_R105_LATER_AMENDMENTS_13_15=NOT_RECONCILED\n";
    echo "PI_R105_RKA_DPA_REALIZATION=NOT_VERIFIED\n";
    echo "PI_R105_REVIEWER_APPROVAL=NOT_REQUESTED\n";
    echo "PI_R105_FISCAL_ADMISSION=ZERO\n";
    echo "PI_R105_PRODUCTION_ADMISSION=DENIED\n";
    echo "PI_R105_NO_MUTATIONS=YES\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
