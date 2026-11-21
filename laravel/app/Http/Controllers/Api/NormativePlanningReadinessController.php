<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * R71: read-only cross-module readiness for a normative-to-planning crosswalk.
 * Never infers legal applicability from a citation or a planning document title.
 */
final class NormativePlanningReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dependency = 'planning_lineage';
        try {
            $lineageResponse = app(PlanningLineageController::class)->__invoke();
            if ($lineageResponse->getStatusCode() !== 200) {
                throw new RuntimeException('Official planning lineage unavailable.');
            }

            $lineage = json_decode($lineageResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $dependency = 'normative_ancestry_cache';
            $graph = Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'))
                ->get(OfficialNormativeAncestry::CACHE_KEY);

            if (!config('pi.regulatory_auto_ancestry.enabled', true)
                || !is_array($graph)
                || ($graph['schema'] ?? null) !== 'PI_R70R_AUTOMATED_ANCESTRY_V1'
                || ($graph['tenant_id'] ?? null) !== config('pi.tenant_id')
                || ($graph['result_status'] ?? null) !== 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY'
                || ($lineage['tenant_id'] ?? null) !== $graph['tenant_id']
                || !is_array($graph['nodes'] ?? null)
                || !is_array($graph['edges'] ?? null)) {
                throw new RuntimeException('Normative evidence cache is unavailable or incompatible.');
            }

            $dependency = 'evidence_contract';
            $unresolved = count(array_filter($graph['nodes'], static fn (mixed $node): bool =>
                is_array($node)
                && in_array($node['official_resolution'] ?? null, [
                    'CITED_IDENTITY_NOT_YET_RESOLVED',
                    'SOURCE_UNAVAILABLE',
                ], true)
            ));

            $documents = array_map(static fn (array $document): array => [
                'id' => $document['id'],
                'kind' => $document['kind'],
                'issued_on' => $document['issued_on'],
                'metadata_url' => $document['metadata_url'],
                'pdf_snapshot_state' => $document['pdf_snapshot_state'],
            ], $lineage['documents']);

            $sourceDocuments = array_column($lineage['documents'], null, 'id');
            $candidateEdges = [];
            foreach ($lineage['relations'] as $relation) {
                if ($relation['type'] !== 'PLANNING_DOCUMENT_NORMATIVE_REFERENCE') {
                    continue;
                }
                $source = $sourceDocuments[$relation['source_document_id']] ?? null;
                if (!is_array($source)) {
                    throw new RuntimeException('Normative candidate source is absent.');
                }
                $target = $sourceDocuments[$relation['to']] ?? null;
                if (!is_array($target)) {
                    throw new RuntimeException('Normative candidate planning target is absent.');
                }
                $sourcePostdatesTarget = $source['issued_on'] > $target['issued_on'];
                $candidateEdges[] = [
                    'id' => $relation['id'],
                    'from_document_id' => $relation['from'],
                    'to_planning_document_id' => $relation['to'],
                    'source_document_id' => $source['id'],
                    'source_metadata_url' => $source['metadata_url'],
                    'provision_locator' => $relation['source_locator'],
                    'evidence_summary' => $relation['evidence_summary'],
                    'pdf_snapshot_state' => $source['pdf_snapshot_state'],
                    'relation_type' => $relation['type'],
                    'source_issued_on' => $source['issued_on'],
                    'target_issued_on' => $target['issued_on'],
                    'source_postdates_target' => $sourcePostdatesTarget,
                    'temporal_review_status' => $sourcePostdatesTarget
                        ? 'FINAL_INSTRUMENT_POSTDATES_TARGET_REVIEW_DRAFT_REFERENCE'
                        : 'NO_POSTDATING_OBSERVED_NOT_LEGAL_VALIDATION',
                    'evidence_status' => 'METADATA_LOCATOR_ONLY_PDF_NOT_PINNED',
                    'mapping_status' => 'MAPPING_CANDIDATE',
                    'review_status' => 'REVIEW_REQUIRED',
                    'legal_effect' => 'NOT_ASSESSED',
                ];
            }

            $versionCandidates = $graph['version_candidates'] ?? [];
            if (!is_array($versionCandidates) || count($versionCandidates) > 25) {
                throw new RuntimeException('Amendment candidate contract invalid.');
            }
            $amendmentWatch = [];
            foreach ($versionCandidates as $candidate) {
                if (!is_array($candidate)
                    || !preg_match('/^UU-[1-9][0-9]*-[0-9]{4}$/', (string) ($candidate['instrument_id'] ?? ''))
                    || !preg_match('/^UU-[1-9][0-9]*-[0-9]{4}$/', (string) ($candidate['amending_candidate_id'] ?? ''))
                    || ($candidate['relation'] ?? null) !== 'OFFICIAL_STATUS_LISTED_AS_AMENDING_CANDIDATE'
                    || ($candidate['legal_effect'] ?? null) !== 'NOT_ASSESSED'
                    || !preg_match('/^[A-Fa-f0-9]{64}$/', (string) ($candidate['source_html_sha256'] ?? ''))) {
                    throw new RuntimeException('Untrusted amendment candidate.');
                }
                $url = (string) ($candidate['evidence_url'] ?? '');
                $parts = parse_url($url);
                if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
                    || ($parts['host'] ?? null) !== 'peraturan.bpk.go.id'
                    || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                    throw new RuntimeException('Untrusted amendment source.');
                }
                $amendmentWatch[] = [
                    'instrument_id' => $candidate['instrument_id'],
                    'amending_candidate_id' => $candidate['amending_candidate_id'],
                    'official_evidence_url' => $url,
                    'source_html_sha256' => $candidate['source_html_sha256'],
                    'status' => 'UNLINKED_AMENDMENT_CANDIDATE_REVIEW_REQUIRED',
                    'planning_impact' => 'NOT_ASSESSED_NO_VERIFIED_PROVISION_EDGE',
                ];
            }

            // Neither R70J nor R70U establishes a provision-level, time-scoped
            // APPLIES_TO edge between these documents and a normative provision.
            // No legal alignment can be calculated from the available evidence.
            return response()->json([
                'schema' => 'PI_R71_NORMATIVE_PLANNING_READINESS_V1',
                'tenant_id' => $lineage['tenant_id'],
                'planning_year' => $lineage['planning_year'],
                'planning_source_sha256' => $lineage['source_registry_sha256'],
                'normative_source' => $graph['source'] ?? null,
                'documents' => $documents,
                'normative_node_count' => count($graph['nodes']),
                'normative_citation_count' => count($graph['edges']),
                'unresolved_normative_identity_count' => $unresolved,
                'verified_provision_to_planning_edges' => [],
                'candidate_provision_to_planning_edges' => $candidateEdges,
                'candidate_edge_count' => count($candidateEdges),
                'upstream_amendment_watch' => $amendmentWatch,
                'upstream_amendment_watch_count' => count($amendmentWatch),
                'crosswalk_status' => 'NO_VERIFIED_PROVISION_TO_PLANNING_EDGE',
                'readiness' => 'ABSTAIN',
                'blockers' => [
                    'NORMATIVE_APPLICABILITY_NOT_PROVEN',
                    'AMENDMENT_CHAIN_NOT_EXHAUSTIVELY_VERIFIED',
                    'PLANNING_PDF_SNAPSHOTS_NOT_PINNED',
                ],
                'review_required' => true,
                'legal_harmonization' => 'NOT_ASSESSED',
                'numeric_comparison' => 'BLOCKED',
                'production_admission' => 'DENIED',
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'NORMATIVE_PLANNING_EVIDENCE_UNAVAILABLE',
                'dependency' => $dependency,
                'recovery' => $dependency === 'normative_ancestry_cache'
                    ? 'RUN_USER_TRIGGERED_ANCESTRY_THEN_RELOAD_REVIEW'
                    : 'CHECK_SOURCE_OR_CONTRACT_BEFORE_RETRY',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
