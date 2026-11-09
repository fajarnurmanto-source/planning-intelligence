<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Source-only AI context: reuses the canonical R70F hash-verified regulatory
 * projection, never calls a provider or asserts legal/numeric conclusions.
 */
final class AiEvidenceContextController extends Controller
{
    public function __invoke(RegulatoryChainProjector $projector): JsonResponse
    {
        try {
            $model = $projector->project(
                (string) config('pi.regulatory.ledger_path'),
                (string) config('pi.regulatory.scope_path'),
            );

            $documents = [];
            foreach ($model['documents'] as $document) {
                $evidence = [];
                foreach ($document['source_evidence'] as $item) {
                    $evidence[] = $this->anchor($item);
                }
                foreach ($document['scope_targets'] as $target) {
                    foreach ($target['evidence'] as $item) {
                        $evidence[] = $this->anchor($item);
                    }
                }
                foreach ($document['date_candidates'] as $date) {
                    foreach ($date['evidence'] as $item) {
                        $evidence[] = $this->anchor($item);
                    }
                }
                $documents[] = [
                    'source_id' => $document['id'],
                    'title' => $document['title'],
                    'source_url' => $document['source_url'],
                    'metadata_url' => $document['metadata_url'],
                    'pdf_sha256' => $document['pdf_sha256'],
                    'review_status' => $document['review_status'],
                    'evidence_anchors' => $evidence,
                ];
            }

            return response()->json([
                'schema' => 'PI_R69A_LARAVEL_AI_EVIDENCE_CONTEXT_V1',
                'tenant_id' => $model['tenant_id'],
                'source_schema' => $model['schema'],
                'source_integrity' => $model['source_integrity'],
                'summary' => $model['summary'],
                'documents' => $documents,
                'ai_provider_called' => false,
                'context_only' => true,
                'reviewer_gate' => 'REQUIRED',
                'legal_harmonization' => 'NOT_ASSESSED',
                'numeric_comparison' => 'BLOCKED',
                'production_admission' => 'DENIED',
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'AI_EVIDENCE_CONTEXT_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    /** @param array<string, mixed> $item */
    private function anchor(array $item): array
    {
        return [
            'source_id' => $item['source_id'],
            'pdf_sha256' => $item['pdf_sha256'],
            'pdf_page' => $item['pdf_page'],
            'excerpt_sha256' => $item['excerpt_sha256'],
            'review_status' => $item['review_status'],
        ];
    }
}
