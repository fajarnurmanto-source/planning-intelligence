<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Deterministic, evidence-grounded reviewer briefing. No AI inference,
 * legal conclusion, numeric comparison, or outbound provider request.
 */
final class AiReviewBriefController extends Controller
{
    public function __invoke(RegulatoryChainProjector $projector): JsonResponse
    {
        try {
            $model = $projector->project(
                (string) config('pi.regulatory.ledger_path'),
                (string) config('pi.regulatory.scope_path'),
            );

            $sources = [];
            foreach ($model['documents'] as $document) {
                $anchors = [];
                foreach ($document['source_evidence'] as $anchor) {
                    $anchors[] = $this->reference($anchor);
                }
                foreach ($document['scope_targets'] as $target) {
                    foreach ($target['evidence'] as $anchor) {
                        $anchors[] = $this->reference($anchor);
                    }
                }
                foreach ($document['date_candidates'] as $date) {
                    foreach ($date['evidence'] as $anchor) {
                        $anchors[] = $this->reference($anchor);
                    }
                }

                $sources[] = [
                    'source_id' => $document['id'],
                    'title' => $document['title'],
                    'official_source_url' => $document['source_url'],
                    'pdf_sha256' => $document['pdf_sha256'],
                    'review_status' => $document['review_status'],
                    'evidence_anchors' => $anchors,
                ];
            }

            return response()->json([
                'schema' => 'PI_R69A_LARAVEL_REVIEW_BRIEF_V1',
                'tenant_id' => $model['tenant_id'],
                'brief_type' => 'EVIDENCE_REVIEW_PACKET_NOT_AI_CONCLUSION',
                'source_integrity' => $model['source_integrity'],
                'source_summary' => $model['summary'],
                'sources' => $sources,
                'review_questions' => [
                    'Verify legal force and version applicability against official documents.',
                    'Verify delegation, article references, and upper-law consistency.',
                    'Reconcile amendment annexes before numeric or budget comparison.',
                ],
                'restrictions' => [
                    'human_review' => 'REQUIRED',
                    'ai_provider_called' => false,
                    'legal_harmonization' => 'NOT_ASSESSED',
                    'numeric_comparison' => 'BLOCKED',
                    'production_admission' => 'DENIED',
                ],
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'AI_REVIEW_BRIEF_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    /** @param array<string, mixed> $anchor */
    private function reference(array $anchor): array
    {
        return [
            'source_id' => $anchor['source_id'],
            'pdf_page' => $anchor['pdf_page'],
            'excerpt_sha256' => $anchor['excerpt_sha256'],
            'review_status' => $anchor['review_status'],
        ];
    }
}
