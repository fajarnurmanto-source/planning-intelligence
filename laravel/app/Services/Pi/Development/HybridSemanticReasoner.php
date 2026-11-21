<?php

namespace App\Services\Pi\Development;

/**
 * PI-HYBRID-001: deterministic boundary for future semantic AI proposals.
 *
 * No provider call, persistence, reviewer decision or source acquisition occurs here.
 * A proposed semantic relationship is not a legal or numerical conclusion.
 */
final class HybridSemanticReasoner
{
    private const RELATIONS = ['EQUIVALENT', 'BROADER', 'NARROWER', 'RELATED', 'CONFLICT', 'INSUFFICIENT'];
    private const CONFIDENCE = ['LOW', 'MEDIUM', 'HIGH', 'UNDETERMINED'];

    /**
     * @param array<string,mixed> $crosswalk Canonical R88 read model.
     * @param array<string,mixed>|null $proposal Internal adapter result; NOT a browser request.
     * @return array<string,mixed>
     */
    public function read(array $crosswalk, ?array $proposal = null): array
    {
        $filters = $crosswalk['filters'] ?? [];
        $result = [
            'schema' => 'PI_HYBRID_SEMANTIC_BOUNDARY_V1',
            'status' => 'ABSTAIN_PROVIDER_NOT_CONFIGURED',
            'provider_execution' => 'NOT_RUN',
            'model_id' => null,
            'context' => [
                'tenant_id' => $crosswalk['tenant_id'] ?? null,
                'year' => $filters['year'] ?? null,
                'as_of' => $filters['as_of'] ?? null,
                'code' => $filters['code'] ?? null,
            ],
            'candidates' => [],
            'semantic_confidence' => 'NOT_ASSESSED',
            'evidence_confidence' => 'NOT_ASSESSED',
            'legal_validation_status' => 'NOT_ASSESSED',
            'semantic_confidence_is_probability' => false,
            'gates' => [
                'relation_promotion' => 'NOT_PERFORMED',
                'equivalence_asserted' => false,
                'numeric_comparison' => 'BLOCKED',
                'review_decision' => 'NO_ACTIVE_DECISION',
                'production_admission' => 'DENIED',
            ],
        ];

        $interpretation = $crosswalk['interpretation'] ?? [];
        if (($crosswalk['schema'] ?? null) !== 'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1'
            || ($crosswalk['tenant_id'] ?? null) !== 'ID-DIY-YOG'
            || ($interpretation['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($interpretation['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($interpretation['review_decision'] ?? null) !== 'NO_ACTIVE_DECISION'
            || ($interpretation['production_admission'] ?? null) !== 'DENIED') {
            $result['status'] = 'ABSTAIN_CANONICAL_GUARDS';

            return $result;
        }

        // The 2027 comparison version is not verified, even when a model predicts equivalence.
        if (($crosswalk['result_count'] ?? null) !== 1
            || ($filters['year'] ?? null) !== 2026
            || ($crosswalk['classification']['status'] ?? null) !== 'EXACT_LOCAL_OBSERVATION'
            || ($crosswalk['continuity']['equivalence_asserted'] ?? true) !== false) {
            $result['status'] = 'ABSTAIN_NO_VERSIONED_CASE';

            return $result;
        }

        if ($proposal === null) {
            return $result;
        }

        $result['provider_execution'] = 'INJECTED_INTERNAL_PROPOSAL';
        $relation = $proposal['semantic_relation'] ?? null;
        $confidence = $proposal['semantic_confidence'] ?? null;
        $modelId = $proposal['model_id'] ?? null;
        $sourceId = $proposal['source_node_id'] ?? null;
        $targetId = $proposal['target_node_id'] ?? null;
        $edgeIds = $proposal['supporting_relation_ids'] ?? null;
        $explanation = $proposal['explanation'] ?? null;

        if (! is_string($modelId)
            || preg_match('/\\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,127}\\z/', $modelId) !== 1
            || ! in_array($relation, self::RELATIONS, true)
            || ! in_array($confidence, self::CONFIDENCE, true)
            || ! is_string($sourceId) || ! is_string($targetId) || $sourceId === $targetId
            || ! is_string($explanation) || trim($explanation) === ''
            || strlen($explanation) > 1000
            || ($proposal['year'] ?? null) !== $filters['year']
            || ($proposal['as_of'] ?? null) !== $filters['as_of']
            || ($proposal['code'] ?? null) !== $filters['code']
            || ! is_array($edgeIds) || ! array_is_list($edgeIds)
            || count($edgeIds) < 1 || count($edgeIds) > 8
            || count(array_filter($edgeIds, 'is_string')) !== count($edgeIds)
            || count(array_unique($edgeIds)) !== count($edgeIds)) {
            $result['status'] = 'ABSTAIN_INVALID_SEMANTIC_PROPOSAL';

            return $result;
        }

        $knownNodes = array_column($crosswalk['nodes'] ?? [], 'id');
        if (! in_array($sourceId, $knownNodes, true) || ! in_array($targetId, $knownNodes, true)) {
            $result['status'] = 'ABSTAIN_UNKNOWN_EVIDENCE_NODE';

            return $result;
        }

        $edgesById = [];
        foreach (($crosswalk['relations'] ?? []) as $edge) {
            if (is_array($edge) && is_string($edge['id'] ?? null)) {
                $edgesById[$edge['id']] = $edge;
            }
        }

        // Supporting edge IDs must form one directed, documented path in the R88 graph.
        $cursor = $sourceId;
        $allPinned = true;
        foreach ($edgeIds as $id) {
            $edge = is_string($id) ? ($edgesById[$id] ?? null) : null;
            $provenance = is_array($edge) ? ($edge['provenance'] ?? null) : null;
            if (! is_array($edge) || ($edge['from'] ?? null) !== $cursor
                || ! is_array($provenance)
                || ! is_string($provenance['locator'] ?? null) || trim($provenance['locator']) === ''
                || ! is_string($provenance['source_url'] ?? null) || trim($provenance['source_url']) === ''
                || ! is_string($provenance['as_of_date'] ?? null)
                || $provenance['as_of_date'] > $filters['as_of']) {
                $result['status'] = 'ABSTAIN_UNSUPPORTED_GRAPH_PATH';

                return $result;
            }
            $cursor = $edge['to'] ?? null;
            $hash = $provenance['source_sha256'] ?? null;
            $allPinned = $allPinned && is_string($hash) && preg_match('/\\A[A-Fa-f0-9]{64}\\z/', $hash) === 1
                && ($provenance['verification_status'] ?? null) === 'VERIFIED_SOURCE_BYTES';
        }
        if ($cursor !== $targetId) {
            $result['status'] = 'ABSTAIN_UNSUPPORTED_GRAPH_PATH';

            return $result;
        }

        if ($relation === 'INSUFFICIENT') {
            $result['status'] = 'ABSTAIN_SEMANTIC_INSUFFICIENT';

            return $result;
        }

        $evidenceStrength = $allPinned ? 'PINNED_SOURCE_BYTES' : 'PARTIAL_OR_UNPINNED_SOURCE';
        $result['status'] = 'CANDIDATE_REVIEW_REQUIRED';
        $result['model_id'] = $modelId;
        $result['semantic_confidence'] = $confidence;
        $result['evidence_confidence'] = $evidenceStrength;
        $result['candidates'] = [[
            'source_node_id' => $sourceId,
            'target_node_id' => $targetId,
            'semantic_relation' => $relation,
            'supporting_relation_ids' => $edgeIds,
            'explanation' => trim($explanation),
            'semantic_confidence' => $confidence,
            'evidence_confidence' => $evidenceStrength,
            'legal_validation_status' => 'NOT_ASSESSED',
            'review_status' => 'CANDIDATE_REVIEW_REQUIRED',
            'equivalence_asserted' => false,
        ]];

        return $result;
    }
}
