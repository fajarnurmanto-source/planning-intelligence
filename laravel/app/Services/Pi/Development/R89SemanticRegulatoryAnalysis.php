<?php

namespace App\Services\Pi\Development;

use Throwable;

/**
 * R89 semantic candidate vertical slice composed with canonical R88.
 * Never accepts model-chosen graph IDs, legal authority or year as evidence.
 */
final class R89SemanticRegulatoryAnalysis
{
    public function __construct(
        private readonly RegulatoryPlanningCrosswalk $crosswalk,
        private readonly HybridSemanticReasoner $validator,
        private readonly SemanticCandidateProvider $provider,
    ) {}

    /** @param array<string,mixed> $filters
     *  @return array<string,mixed>
     */
    public function analyze(array $filters): array
    {
        $canonical = $this->crosswalk->read($filters);
        $boundary = $this->validator->read($canonical);
        $result = [
            'schema' => 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1',
            'tenant_id' => $canonical['tenant_id'],
            'filters' => $canonical['filters'],
            'context_class' => 'QA_DOCUMENTARY_TERMINOLOGY_NOT_VERIFIED_PROVISION_TEXT',
            'source_registry_sha256' => $canonical['source_registry_sha256'],
            'definitions_registry_sha256' => $canonical['definitions_registry_sha256'],
            'context_sha256' => null,
            'semantic_reasoning' => $boundary,
            'reviewer_url' => $canonical['reviewer_url'],
            'source_rights' => 'LOCAL_ONLY_NO_CLOUD_TRANSFER',
        ];

        // Before enabling any provider, the 2026 canonical graph and legal
        // gates must pass; 2027, missing sources, or unknown years abstain.
        if ($boundary['status'] !== 'ABSTAIN_PROVIDER_NOT_CONFIGURED') {
            return $result;
        }
        if (! config('pi.semantic_local.enabled', false)) {
            return $result;
        }
        if (! app()->environment(['local', 'testing'])) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_LOCAL_RUNTIME_REQUIRED';

            return $result;
        }
        $model = (string) config('pi.semantic_local.model', '');
        if (preg_match('~\A[A-Za-z0-9_.:/-]{1,100}\z~D', $model) !== 1) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_LOCAL_MODEL_NOT_CONFIGURED';

            return $result;
        }
        $definition = $canonical['operational_definitions'][0] ?? null;
        if (! is_array($definition) || ! is_string($definition['preferred_term'] ?? null)
            || ! is_string($definition['observed_term'] ?? null)
            || ! is_array($definition['supporting_edge_ids'] ?? null)
            || count($definition['supporting_edge_ids']) < 1
            || count($definition['supporting_edge_ids']) > 8) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_NO_EVIDENCE_BOUND_TERMINOLOGY';

            return $result;
        }

        $byId = [];
        foreach ($canonical['relations'] as $relation) {
            $byId[$relation['id']] = $relation;
        }
        $path = [];
        foreach ($definition['supporting_edge_ids'] as $id) {
            if (! is_string($id) || ! isset($byId[$id])) {
                $result['semantic_reasoning']['status'] = 'ABSTAIN_INVALID_DEFINITION_EVIDENCE';

                return $result;
            }
            $path[] = $byId[$id];
        }
        $first = $path[0];
        $last = $path[count($path) - 1];
        // Structural preflight uses the same canonical validator, before any
        // model call. This preflight result is never published as AI output.
        $preflight = [
            'model_id' => 'r89-structural-preflight',
            'year' => $canonical['filters']['year'],
            'as_of' => $canonical['filters']['as_of'],
            'code' => $canonical['filters']['code'],
            'source_node_id' => $first['from'],
            'target_node_id' => $last['to'],
            'supporting_relation_ids' => $definition['supporting_edge_ids'],
            'semantic_relation' => 'RELATED',
            'semantic_confidence' => 'UNDETERMINED',
            'explanation' => 'Structural verification only; not a model inference.',
        ];
        if ($this->validator->read($canonical, $preflight)['status'] !== 'CANDIDATE_REVIEW_REQUIRED') {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_INVALID_EVIDENCE_PATH';

            return $result;
        }

        $anchors = [];
        foreach ($path as $edge) {
            $p = $edge['provenance'];
            $anchors[] = [
                'edge_id' => $edge['id'],
                'locator' => mb_substr($p['locator'], 0, 250),
                'verification_status' => $p['verification_status'],
                'source_sha256' => $p['source_sha256'] ?? null,
                'source_date' => $p['as_of_date'],
            ];
        }
        $context = [
            'case_year' => $canonical['filters']['year'],
            'as_of' => $canonical['filters']['as_of'],
            'code' => $canonical['filters']['code'],
            'preferred_term' => mb_substr($definition['preferred_term'], 0, 300),
            'observed_term' => mb_substr($definition['observed_term'], 0, 300),
            'observed_relation' => 'RELATED_CANDIDATE_NOT_IDENTICAL',
            'scope' => 'DOCUMENTARY_TERMINOLOGY_ONLY_NO_PRIMARY_PROVISION_TEXT',
            'evidence' => $anchors,
        ];
        $encoded = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result['context_sha256'] = strtoupper(hash('sha256', $encoded));

        try {
            $candidate = $this->provider->propose($context, $model);
            // Never trust or forward model-supplied graph IDs, tenant, source,
            // context, approval, legal verdict or confidence percentage.
            $proposal = $preflight;
            $proposal['model_id'] = 'ollama-local:'.$model;
            $proposal['semantic_relation'] = $candidate['semantic_relation'] ?? null;
            $proposal['semantic_confidence'] = $candidate['semantic_confidence'] ?? null;
            $proposal['explanation'] = $candidate['explanation'] ?? null;
            $result['semantic_reasoning'] = $this->validator->read($canonical, $proposal);
            $result['semantic_reasoning']['provider_execution'] = 'OLLAMA_LOCAL_CALLED';
        } catch (Throwable) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_LOCAL_PROVIDER_UNAVAILABLE';
            $result['semantic_reasoning']['provider_execution'] = 'OLLAMA_LOCAL_ATTEMPTED';
        }

        return $result;
    }
}
