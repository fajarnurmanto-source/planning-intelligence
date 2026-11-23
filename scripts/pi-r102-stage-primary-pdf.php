<?php
declare(strict_types=1);

/**
 * PI R102 OFFLINE staging CLI, no HTTP requests and no Laravel boot.
 *
 * php scripts/pi-r102-stage-primary-pdf.php
 *   --id=YOG-APBD-DETAIL-2026
 *   --file=/isolated/download/2025pw3405074.pdf
 *   --url=https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf
 *   --captured-at=2026-11-23T21:00:00+07:00
 *   --staging-root=/isolated/quarantine
 *   [--commit]
 *
 * Default dry-run only calculates SHA256 and assesses PDF magic/EOF.
 * --commit copies to a distinct immutable candidate archive OUTSIDE any
 * Laravel app and writes one receipt, but never calls a cloud API, accesses
 * user accounts/SQLite, or approves evidence.
 */

use App\Services\Pi\Development\PrimaryPdfCandidateIntake;

$root = dirname(__DIR__);
$servicePath = $root.'/laravel/app/Services/Pi/Development/PrimaryPdfCandidateIntake.php';
// R103: this offline service has NO Laravel framework dependencies.
// It works safely from a clean QA Git worktree with NO vendor/ directory,
// and never needs Composer install or Laravel boot on the protected app.
if (! is_file($servicePath) || is_link($servicePath)) {
    fwrite(STDERR, "PI_R103_OFFLINE_SERVICE_SOURCE_UNAVAILABLE\n");
    exit(2);
}
require_once $servicePath;

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--commit') {
        $args['commit'] = true;
        continue;
    }
    if (! str_starts_with($arg, '--') || ! str_contains($arg, '=')) {
        fwrite(STDERR, "PI_R102_UNKNOWN_ARG_HOLD\n");
        exit(2);
    }
    [$name, $value] = explode('=', substr($arg, 2), 2);
    if (! in_array($name, ['id', 'file', 'url', 'captured-at', 'staging-root'], true)
        || $value === '' || array_key_exists($name, $args)) {
        fwrite(STDERR, "PI_R102_INVALID_OPTION_HOLD\n");
        exit(2);
    }
    $args[$name] = $value;
}
foreach (['id', 'file', 'url', 'captured-at', 'staging-root'] as $required) {
    if (! isset($args[$required])) {
        fwrite(STDERR, 'PI_R102_MISSING_OPTION_'.strtoupper($required)."\n");
        exit(2);
    }
}
$projectRoot = realpath($root);
$stageRoot = realpath($args['staging-root']);
$fileSource = realpath($args['file']);
if ($projectRoot === false || $stageRoot === false || $fileSource === false) {
    fwrite(STDERR, "PI_R102_SOURCE_OR_STAGE_NOT_FOUND\n");
    exit(2);
}
$normalizedProject = strtolower(str_replace('\\', '/', $projectRoot));
$normalizedStage = strtolower(str_replace('\\', '/', $stageRoot));
$normalizedFile = strtolower(str_replace('\\', '/', $fileSource));
$protectedOriginal = strtolower('D:/Research/PlanningIntelligence/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel');

foreach ([$normalizedProject, $protectedOriginal] as $forbidden) {
    if ($normalizedStage === $forbidden || str_starts_with($normalizedStage, $forbidden.'/')) {
        fwrite(STDERR, "PI_R102_STAGE_INSIDE_APPLICATION_FORBIDDEN\n");
        exit(2);
    }
}
if ($normalizedStage === $normalizedFile || str_starts_with($normalizedStage, $normalizedFile.'/')) {
    fwrite(STDERR, "PI_R102_STAGE_SOURCE_PATH_UNSAFE\n");
    exit(2);
}

try {
    $intake = new PrimaryPdfCandidateIntake($root.'/laravel/resources/pi/official-source-metadata-r100.json');
    $result = $intake->stage(
        $args['id'],
        $args['file'],
        $args['url'],
        $args['captured-at'],
        $args['staging-root'],
        $args['commit'] ?? false
    );
    $receipt = $result['receipt'];
    fwrite(STDOUT, "PI_R102_OFFICIAL_ID=".$receipt['source_id']."\n");
    fwrite(STDOUT, "PI_R102_LOCAL_BYTES=".$receipt['byte_length']."\n");
    fwrite(STDOUT, "PI_R102_LOCAL_SHA256=".$receipt['byte_sha256']."\n");
    fwrite(STDOUT, "PI_R102_OFFICIAL_ORIGIN_LINK=NOT_AUTHENTICATED\n");
    fwrite(STDOUT, "PI_R102_DOCUMENT_IDENTITY=REVIEW_REQUIRED\n");
    fwrite(STDOUT, "PI_R102_REVIEWER_APPROVAL=NOT_REQUESTED\n");
    fwrite(STDOUT, "PI_R102_SUBACTIVITY_AMOUNTS_ADMITTED=0\n");
    fwrite(STDOUT, "PI_R102_PRODUCTION_ADMISSION=DENIED\n");
    if ($args['commit'] ?? false) {
        fwrite(STDOUT, "PI_R102_QUARANTINED_PDF=".$result['pdf_path']."\n");
        fwrite(STDOUT, "PI_R102_IMMUTABLE_RECEIPT=".$result['receipt_path']."\n");
        fwrite(STDOUT, "PI_R102_LOCAL_PDF_CANDIDATE_STAGED=PASS\n");
    } else {
        fwrite(STDOUT, "PI_R102_DRY_RUN=PASS_NO_WRITE\n");
    }
} catch (Throwable $failure) {
    fwrite(STDERR, $failure->getMessage()."\n");
    exit(1);
}
