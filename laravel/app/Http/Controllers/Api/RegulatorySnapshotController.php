<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use App\Services\PlanningIntelligence\RegulatoryPdfSnapshotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;

/** Laravel-only R70B catalog and local PDF snapshot access, reviewer QA. */
final class RegulatorySnapshotController extends Controller
{
    public function sources(
        RegulatoryChainProjector $projector,
        RegulatoryPdfSnapshotRegistry $registry
    ): JsonResponse {
        try {
            $model = $this->verified($projector);
            $available = $registry->verifiedFiles($model);
            $items = [];
            $anomalies = 0;
            foreach ($model['documents'] as $doc) {
                $warning = $doc['metadata_warning'] ?? null;
                if (is_string($warning) && trim($warning) !== '') {
                    $anomalies++;
                }
                $hasPdf = isset($available[$doc['id']]);
                $items[] = [
                    'id' => $doc['id'],
                    'title' => $doc['title'],
                    'sequence' => $doc['sequence'],
                    'source_url' => $doc['source_url'],
                    'metadata_url' => $doc['metadata_url'],
                    'status' => $hasPdf ? 'ACQUIRED_IDENTITY_VERIFIED' : 'ACQUIRED_NEEDS_REVIEW',
                    'has_local_pdf' => $hasPdf,
                    'sha256' => $doc['pdf_sha256'],
                    'metadata_warning' => $warning,
                    'identity' => $hasPdf ? 'PDF_SHA256_MATCHES_LEDGER' : 'NOT_CHECKED_LOCALLY',
                    'review_status' => 'REVIEW_REQUIRED',
                ];
            }
            return response()->json([
                'schema' => 'PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1',
                'tenant_id' => $model['tenant_id'],
                'total' => count($items),
                'verified' => count($available),
                'metadata_anomalies' => $anomalies,
                'sources' => $items,
                'legal_harmonization' => 'NOT_ASSESSED',
                'numeric_comparison' => 'BLOCKED',
                'production_admission' => 'DENIED',
            ])->header('Cache-Control', 'private, no-store')
              ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'REGULATORY_SNAPSHOT_CATALOG_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    public function pdf(
        Request $request,
        RegulatoryChainProjector $projector,
        RegulatoryPdfSnapshotRegistry $registry
    ): Response|JsonResponse {
        $sourceId = $request->query('source_id');
        if (! is_string($sourceId) || preg_match('/^[A-Z0-9_]{3,64}$/D', $sourceId) !== 1) {
            return response()->json(['status' => 'ABSTAIN', 'code' => 'SOURCE_ID_INVALID'], 400)
                ->header('Cache-Control', 'private, no-store');
        }
        try {
            $model = $this->verified($projector);
            $allowedIds = array_column($model['documents'], 'id');
            if (! in_array($sourceId, $allowedIds, true)) {
                return response()->json(['status' => 'ABSTAIN', 'code' => 'SOURCE_NOT_FOUND'], 404)
                    ->header('Cache-Control', 'private, no-store');
            }
            $available = $registry->verifiedFiles($model);
            if (! isset($available[$sourceId])) {
                return response()->json(['status' => 'ABSTAIN', 'code' => 'PDF_SNAPSHOT_NOT_AVAILABLE'], 404)
                    ->header('Cache-Control', 'private, no-store');
            }

            // Read only previously validated, bounded files and verify bytes
            // once more before returning. Never stream a mutable unchecked path.
            $file = $available[$sourceId];
            $bytes = file_get_contents($file['path']);
            if (! is_string($bytes) ||
                strlen($bytes) !== $file['size'] ||
                strtoupper(hash('sha256', $bytes)) !== $file['sha256'] ||
                ! str_starts_with($bytes, '%PDF-')) {
                throw new RuntimeException('PDF_CHANGED_BEFORE_SEND');
            }
            return response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$sourceId.'.pdf"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'REGULATORY_PDF_SNAPSHOT_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    /** @return array<string, mixed> */
    private function verified(RegulatoryChainProjector $projector): array
    {
        $model = $projector->project(
            (string) config('pi.regulatory.ledger_path'),
            (string) config('pi.regulatory.scope_path')
        );
        if (($model['tenant_id'] ?? null) !== config('pi.tenant_id') ||
            ($model['source_integrity']['scope_links_to_ledger'] ?? null) !== true ||
            ($model['interpretation']['numeric_comparison'] ?? null) !== 'BLOCKED' ||
            ($model['interpretation']['legal_harmonization'] ?? null) !== 'NOT_ASSESSED' ||
            ($model['interpretation']['production_admission'] ?? null) !== 'DENIED') {
            throw new RuntimeException('REGULATORY_QA_GUARD_REJECTED');
        }
        return $model;
    }
}
