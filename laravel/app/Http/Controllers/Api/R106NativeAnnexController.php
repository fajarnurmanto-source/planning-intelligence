<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\BpkQuarantineReadOnlyReview;
use App\Services\Pi\Development\NativePdfAnnexIntelligence;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/** Tenant-authenticated, opt-in, read-only native annex reviewer. */
final class R106NativeAnnexController extends Controller
{
    public function __invoke(NativePdfAnnexIntelligence $reader): JsonResponse
    {
        try {
            $root = self::verifiedRoot();
            $base = $root.DIRECTORY_SEPARATOR.'YOG-APBD-DETAIL-2026'.DIRECTORY_SEPARATOR
                .'e4aaa45f33b7254fdb7bbe3a0bc456999fd2ce3e19d8c07b874c640a5e1dee24.pdf';
            $change = $root.DIRECTORY_SEPARATOR.'YOG-APBD-DETAIL-CHANGE3-2026'.DIRECTORY_SEPARATOR
                .'4e5194e6987468e30406911b277bacca73506b3bf93be413c0c19784ae3825ea.pdf';
            return response()->json($reader->review($base, $change))
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);
            return response()->json([
                'schema' => 'PI_R106_NATIVE_PDF_ANNEX_REVIEW_V1', 'status' => 'ABSTAIN',
                'code' => 'R106_LOCAL_VERIFIED_PDF_OR_TEXT_LAYER_UNAVAILABLE',
                'numeric_admission' => 0, 'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    public static function verifiedRoot(): string
    {
        if (config('pi.native_annex.enabled') !== true) {
            throw new RuntimeException('PI_R106_OPT_IN_NOT_ENABLED');
        }
        $root = config('pi.native_annex.quarantine_root');
        if (!is_string($root) || $root === '') {
            throw new RuntimeException('PI_R106_ISOLATED_ROOT_NOT_CONFIGURED');
        }
        $result = (new BpkQuarantineReadOnlyReview(
            resource_path('pi/r105-owner-bpk-byte-pins.json'),
            resource_path('pi/official-source-metadata-r100.json'),
            resource_path('pi/bpk-perwal12-dlh-0004-fiscal-candidate-r103.json')
        ))->inspect($root);
        if (($result['local_byte_receipt_integrity'] ?? null) !== 'PASS'
            || ($result['numeric_admission'] ?? null) !== 0) {
            throw new RuntimeException('PI_R106_R105_GUARD_NOT_MET');
        }
        return realpath($root) ?: throw new RuntimeException('PI_R106_QUARANTINE_NOT_FOUND');
    }
}
