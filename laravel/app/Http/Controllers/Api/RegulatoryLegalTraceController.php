<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * R79: read-only Laravel projection into the R70A browser trace contract.
 * This is a PARTIAL compatibility adapter, never a legal finding or an
 * assertion that all R69E source evidence was migrated.
 */
final class RegulatoryLegalTraceController extends Controller
{
    public function __invoke(
        RegulatoryChainProjector $projector,
        RegulatoryTaxonomyRegistry $registry
    ): JsonResponse {
        try {
            $model = $projector->project(
                (string) config('pi.regulatory.ledger_path'),
                (string) config('pi.regulatory.scope_path')
            );
            if (($model['tenant_id'] ?? null) !== config('pi.tenant_id') ||
                ($model['source_integrity']['scope_links_to_ledger'] ?? null) !== true ||
                ($model['interpretation']['numeric_comparison'] ?? null) !== 'BLOCKED' ||
                ($model['interpretation']['legal_harmonization'] ?? null) !== 'NOT_ASSESSED' ||
                ($model['interpretation']['production_admission'] ?? null) !== 'DENIED' ||
                ! is_array($model['documents'] ?? null)) {
                throw new RuntimeException('R79_CANONICAL_SOURCE_GUARD_REJECTED');
            }

            // R74 registry verifies known instruments, provision locators,
            // relation endpoints, and allowlisted official URLs. Its
            // provisions are LOCATORS, not hash-pinned primary-text excerpts.
            $seed = $registry->load();
            $nodes = [];
            foreach ($seed['instruments'] as $item) {
                $nodes[$item['id']] = [
                    'id' => $item['id'], 'title' => $item['title'],
                    'node_type' => 'REGULATION', 'kind' => $item['kind'],
                    'evidence_status' => 'R74_OFFICIAL_LINK_NOT_HASH_PINNED',
                ];
            }
            foreach ($seed['provisions'] as $item) {
                $nodes[$item['id']] = [
                    'id' => $item['id'], 'title' => $item['locator'],
                    'node_type' => 'PROVISION_LOCATOR',
                    'owner_id' => $item['instrument_id'],
                    'evidence_status' => 'R74_LOCATOR_NOT_HASH_PINNED',
                ];
            }
            foreach ($model['documents'] as $item) {
                $id = $item['id'] ?? null;
                if (! is_string($id) || isset($nodes[$id]) ||
                    ! is_string($item['title'] ?? null)) {
                    throw new RuntimeException('R79_DOCUMENT_NODE_INVALID');
                }
                $nodes[$id] = [
                    'id' => $id, 'title' => $item['title'],
                    'node_type' => 'LOCAL_SOURCE_SNAPSHOT',
                    'pdf_sha256' => $item['pdf_sha256'],
                    'evidence_status' => 'R70F_LEDGER_HASH_AND_EXCERPT_HASH_VERIFIED',
                ];
            }

            $relationships = [];
            foreach ($seed['relations'] as $relation) {
                if (! isset($nodes[$relation['from']], $nodes[$relation['to']])) {
                    throw new RuntimeException('R79_UNRESOLVED_RELATION');
                }
                $relationships[] = [
                    'source' => $relation['from'],
                    'target' => $relation['to'],
                    'kind' => $relation['type'],
                    'status' => $relation['status'],
                    'evidence' => $relation['locator'],
                    'href' => $relation['source_url'],
                    'caveat' => 'R74: penunjuk naskah resmi terkurasi, belum verifikasi hash kutipan/pasal; reviewer wajib memeriksa bunyi, keberlakuan, dan akibat hukumnya.',
                ];
            }

            $timeline = [];
            foreach ($model['documents'] as $doc) {
                if (! is_int($doc['sequence'] ?? null) || $doc['sequence'] < 1) {
                    continue;
                }
                $timeline[] = [
                    'sequence' => $doc['sequence'],
                    'title' => $doc['title'],
                    'official_href' => $doc['metadata_url'] ?? $doc['source_url'],
                    'status' => 'ORDER_ONLY_NOT_VERSION_RECONCILED',
                ];
            }
            usort($timeline, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

            $findings = [
                [
                    'id' => 'R79-SOURCE-INTEGRITY',
                    'status' => 'REVIEW_REQUIRED',
                    'title' => 'Integritas sumber lokal telah diperiksa; harmonisasi belum dinilai',
                    'detail' => count($model['documents']).' sumber R70F tercatat dengan hash PDF dan referensi kutipan terverifikasi. Kecocokan legal antarperaturan atau versi lampiran belum diperiksa.',
                    'official_href' => null,
                ],
                [
                    'id' => 'R79-NORMATIVE-CANDIDATES',
                    'status' => 'REVIEW_REQUIRED',
                    'title' => 'Relasi norma R74 masih berupa penunjuk resmi terkurasi',
                    'detail' => count($relationships).' relasi bertanda lokasi sumber belum diverifikasi sebagai kesimpulan hukum, tidak boleh dipakai untuk menyatakan validitas, harmonisasi atau berlaku saat ini.',
                    'official_href' => null,
                ],
                [
                    'id' => 'R79-VERSION-GATE',
                    'status' => 'REVIEW_REQUIRED',
                    'title' => 'Urutan perubahan tidak membuktikan versi konsolidasi',
                    'detail' => count($timeline).' metadata perubahan tersedia untuk ditelusuri, tetapi perbandingan lampiran dan angka, pencabutan, dan kondisi temporal masih memerlukan pemeriksaan reviewer.',
                    'official_href' => null,
                ],
            ];

            return response()->json([
                'schema' => 'PI_R70A_REGULATORY_LEGAL_TRACE_QA_V1',
                'tenant_id' => $model['tenant_id'],
                'decision' => 'ABSTAIN',
                'projection_version' => 'R79_LARAVEL_PARTIAL_LEGACY_COMPATIBILITY',
                'canonical_sources' => ['R70F_HASH_LINKED_LEDGER_SCOPE', 'R74_CURATED_OFFICIAL_LOCATORS'],
                'legacy_feature_parity' => 'PARTIAL_NOT_EQUIVALENT',
                'nodes' => array_values($nodes),
                'relationships' => $relationships,
                'findings' => $findings,
                'amendment_timeline' => $timeline,
                'legal_harmonization' => 'NOT_ASSESSED',
                'numeric_comparison' => 'BLOCKED',
                'version_reconciliation' => 'NOT_PERFORMED',
                'review_status' => 'REVIEW_REQUIRED',
                'production_admission' => 'DENIED',
            ])->header('Cache-Control', 'private, no-store')
              ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);
            return response()->json([
                'status' => 'ABSTAIN', 'code' => 'R79_LEGAL_TRACE_NOT_AVAILABLE',
                'legal_harmonization' => 'NOT_ASSESSED',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
