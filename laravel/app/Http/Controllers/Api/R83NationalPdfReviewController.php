<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\R82NationalEvidencePipeline;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * R83: only the two R81 SHA-pinned local PDF snapshots, streamed to an
 * authenticated reviewer. No path supplied by a caller ever reaches disk.
 * A source PDF is not a legal interpretation or verified provision.
 */
final class R83NationalPdfReviewController extends Controller
{
    public function __invoke(
        Request $request,
        R82NationalEvidencePipeline $pipeline,
        R82NationalSourceVerifier $verifier
    ): Response {
        $id = $request->query('source_id');

        $names = [
            'PP-81-2012' => 'PP-81-2012.pdf',
            'UU-18-2008' => 'UU-18-2008.pdf',
        ];

        // Invalid source IDs are not lookup candidates, nor filesystem paths.
        if (! is_string($id) || ! array_key_exists($id, $names)) {
            abort(404);
        }

        try {
            // Verifies BOTH sources, official-source provenance and exact
            // independently pinned SHA-256 values on every PDF request.
            $documents = $verifier->verify($pipeline->snapshotDirectory())['documents'];
            $source = $documents[$id] ?? null;
            if (! is_array($source) || ! is_string($source['path'] ?? null)
                || ! is_file($source['path']) || ! is_readable($source['path'])) {
                throw new RuntimeException('R83_REVIEW_SOURCE_UNAVAILABLE');
            }

            return response()->file($source['path'], [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$names[$id].'"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'no-referrer',
            ]);
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'R83_PINNED_PDF_REVIEW_UNAVAILABLE',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        }
    }
}
