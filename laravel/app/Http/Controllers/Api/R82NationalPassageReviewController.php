<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\R82NationalEvidencePipeline;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use App\Services\Pi\Regulatory\R84EvidenceFingerprint;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/** R82: read-only, tenant-protected review of an explicitly prepared QA report. */
final class R82NationalPassageReviewController extends Controller
{
    public function __invoke(
        R82NationalEvidencePipeline $pipeline,
        R82NationalSourceVerifier $verifier,
        R84EvidenceFingerprint $fingerprint
    ): JsonResponse {
        try {
            $source = $verifier->verify($pipeline->snapshotDirectory());
            $path = $pipeline->outputPath();
            if (! is_file($path) || ! is_readable($path) || filesize($path) > 512 * 1024) {
                throw new RuntimeException('R82_REVIEW_REPORT_NOT_PREPARED');
            }
            $report = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($report)
                || ($report['schema'] ?? null) !== 'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1'
                || ($report['tenant_id'] ?? null) !== 'ID-DIY-YOG'
                || ($report['r81_manifest_sha256'] ?? null) !== $source['manifest_sha256']
                || ($report['source_snapshot_integrity'] ?? null) !== 'R81_TWO_INDEPENDENT_SHA256_PINS_MATCH'
                || ($report['r80_candidate_article_count'] ?? null) !== 7
                || ($report['text_candidate_article_count'] ?? -1) < 0
                || ($report['text_candidate_article_count'] ?? 8) > 7
                || ($report['unresolved_article_count'] ?? -1) + ($report['text_candidate_article_count'] ?? -1) !== 7
                || ! is_array($report['sources'] ?? null) || count($report['sources']) !== 2
                || ! is_array($report['candidates'] ?? null) || count($report['candidates']) !== 7
                || ($report['review_status'] ?? null) !== 'REVIEW_REQUIRED'
                || ($report['passage_verification'] ?? null) !== 'UNREVIEWED_TEXT_LAYER_CANDIDATES_ONLY'
                || ($report['relation_admission'] ?? null) !== 'NOT_PERFORMED'
                || ($report['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
                || ($report['numeric_comparison'] ?? null) !== 'BLOCKED'
                || ($report['production_admission'] ?? null) !== 'DENIED') {
                throw new RuntimeException('R82_REVIEW_REPORT_CONTRACT_REJECTED');
            }

            $seenSources = [];
            foreach ($report['sources'] as $item) {
                $id = $item['instrument_id'] ?? null;
                if (! is_string($id) || ! isset($source['documents'][$id])
                    || isset($seenSources[$id])
                    || ($item['pdf_sha256'] ?? null) !== $source['documents'][$id]['sha256']
                    || ($item['acquisition_url'] ?? null) !== $source['documents'][$id]['acquisition_url']
                    || ($item['text_layer_status'] ?? null) !== 'LOCAL_TEXT_LAYER_EXTRACTED') {
                    throw new RuntimeException('R82_REVIEW_SOURCE_DRIFT_OR_DUPLICATE');
                }
                $seenSources[$id] = true;
            }

            $seenArticles = [];
            foreach ($report['candidates'] as $item) {
                $article = $item['article'] ?? null;
                if (! is_int($article) || isset($seenArticles[$article])
                    || ($item['target_provision_id'] ?? null) !== 'UU-18-2008:PASAL-'.$article
                    || ($item['review_status'] ?? null) !== 'REVIEW_REQUIRED'
                    || ($item['relation_admission'] ?? null) !== 'NOT_PERFORMED'
                    || ($item['legal_effect'] ?? null) !== 'NOT_ASSESSED'
                    || ! is_array($item['uu_passages'] ?? null)) {
                    throw new RuntimeException('R82_REVIEW_CANDIDATE_INVALID');
                }
                $seenArticles[$article] = true;
                $fragments = $item['uu_passages'];
                if ($item['pp_reference'] !== null) {
                    $fragments[] = $item['pp_reference'];
                }
                foreach ($fragments as $fragment) {
                    if (! is_array($fragment) || ! is_string($fragment['excerpt'] ?? null)
                        || strlen($fragment['excerpt']) > 2000
                        || ($fragment['excerpt_sha256'] ?? null) !==
                            strtoupper(hash('sha256', $fragment['excerpt']))
                        || ! is_int($fragment['pdf_page'] ?? null)
                        || $fragment['pdf_page'] < 1) {
                        throw new RuntimeException('R82_REVIEW_EXCERPT_HASH_INVALID');
                    }
                }
            }
            // Add an exact server-calculated fingerprint to the HTTP read model.
            // This does NOT change the immutable R82 candidate-manifest.json.
            foreach ($report['candidates'] as $index => $candidate) {
                $report['candidates'][$index]['review_candidate_sha256'] =
                    $fingerprint->candidate($candidate);
            }

            return response()->json($report)
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);
            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'R82_NATIONAL_PASSAGE_REVIEW_UNAVAILABLE',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
