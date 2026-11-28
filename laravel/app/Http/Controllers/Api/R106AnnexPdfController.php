<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Authenticated, immutable, exact-two-source inline PDF reader. */
final class R106AnnexPdfController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse|JsonResponse
    {
        $filters = $request->validate(['source' => ['required', 'string', 'in:base,change3']]);
        try {
            $root = R106NativeAnnexController::verifiedRoot();
            $sources = [
                'base' => ['YOG-APBD-DETAIL-2026', 'e4aaa45f33b7254fdb7bbe3a0bc456999fd2ce3e19d8c07b874c640a5e1dee24', 'Perwal-74-2025.pdf'],
                'change3' => ['YOG-APBD-DETAIL-CHANGE3-2026', '4e5194e6987468e30406911b277bacca73506b3bf93be413c0c19784ae3825ea', 'Perwal-12-2026.pdf'],
            ];
            [$id, $sha, $name] = $sources[$filters['source']];
            $file = $root.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$sha.'.pdf';
            if (is_link($file) || !is_file($file) || !is_readable($file)
                || hash_file('sha256', $file) !== $sha) {
                throw new \RuntimeException('PI_R106_PDF_BYTES_NOT_PINNED');
            }
            return response()->file($file, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$name.'"',
                'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        } catch (Throwable $error) {
            report($error);
            return response()->json([
                'status' => 'ABSTAIN', 'code' => 'R106_VERIFIED_PDF_NOT_AVAILABLE',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
