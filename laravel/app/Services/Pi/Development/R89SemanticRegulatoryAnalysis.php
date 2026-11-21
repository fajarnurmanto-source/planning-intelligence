<?php

namespace App\Services\Pi\Development;

use App\Services\PlanningIntelligence\AiProviderCredentialVault;
use Throwable;

/**
 * Cloud-semantic R89: user-approved, evidence-path-gated candidate proposals.
 * The server owns all node IDs, source integrity, scope and legal guards.
 */
final class R89SemanticRegulatoryAnalysis
{
    public function __construct(
        private readonly RegulatoryPlanningCrosswalk $crosswalk,
        private readonly HybridSemanticReasoner $validator,
        private readonly SemanticCandidateProvider $provider,
        private readonly AiProviderCredentialVault $vault,
    ) {}

    /** @param array<string,mixed> $filters */
    public function preview(array $filters): array
    {
        [, $result] = $this->snapshot($filters);

        return $result;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function analyze(array $filters, string $providerId, string $tenantId, string $approvedSha256): array
    {
        [$canonical, $result, $preflight] = $this->snapshot($filters);
        if ($preflight === null) {
            return $result; // R88 source, year, graph or evidence gate already abstained.
        }

        $digest = $result['preview']['sha256'];
        if (preg_match('/\A[A-Fa-f0-9]{64}\z/D', $approvedSha256) !== 1
            || ! hash_equals($digest, strtoupper($approvedSha256))) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_PREVIEW_STALE';

            return $result;
        }
        if (! config('pi.semantic_cloud.enabled', true)) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_CLOUD_DISABLED_BY_ADMIN';

            return $result;
        }
        if ($tenantId !== $canonical['tenant_id']) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_TENANT_SCOPE_MISMATCH';

            return $result;
        }
        if (! array_key_exists($providerId, AiProviderCredentialVault::PROVIDERS)) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_PROVIDER_NOT_ALLOWED';

            return $result;
        }
        try {
            $configured = $this->vault->resolve($tenantId, $providerId);
        } catch (Throwable) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_PROVIDER_CREDENTIAL_UNAVAILABLE';

            return $result;
        }
        $key = (string) ($configured['api_key'] ?? '');
        $model = (string) ($configured['model'] ?? '');
        if ($key === '' || preg_match('~\A[A-Za-z0-9_.:/-]{1,100}\z~D', $model) !== 1) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_PROVIDER_NOT_CONFIGURED';

            return $result;
        }

        // No user-controlled target URL, model name or evidence IDs are accepted.
        // Only the exact approved bounded terminology+locator preview leaves PI.
        $result['provider'] = $providerId;
        $result['model'] = $model;
        try {
            $suggestion = $this->provider->propose($result['preview']['context'], $providerId, $model, $key);
            $candidate = $preflight;
            $candidate['model_id'] = $providerId.':'.$model;
            $candidate['semantic_relation'] = $suggestion['semantic_relation'] ?? null;
            $candidate['semantic_confidence'] = $suggestion['semantic_confidence'] ?? null;
            $candidate['explanation'] = $suggestion['explanation'] ?? null;
            $result['semantic_reasoning'] = $this->validator->read($canonical, $candidate);
            $result['semantic_reasoning']['provider_execution'] = 'CLOUD_PROVIDER_CALLED';
        } catch (Throwable) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_CLOUD_PROVIDER_UNAVAILABLE';
            $result['semantic_reasoning']['provider_execution'] = 'CLOUD_PROVIDER_ATTEMPTED';
        }

        return $result;
    }

    /** @param array<string,mixed> $filters
     *  @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>|null}
     */
    private function snapshot(array $filters): array
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
            'preview' => ['status' => 'ABSTAIN_CONTEXT_NOT_READY', 'sha256' => null, 'context' => null],
            'provider' => null,
            'model' => null,
            'semantic_reasoning' => $boundary,
            'reviewer_url' => $canonical['reviewer_url'],
            'source_rights' => 'EXPLICIT_APPROVAL_REQUIRED_BEFORE_CLOUD_TRANSFER',
        ];
        if ($boundary['status'] !== 'ABSTAIN_PROVIDER_NOT_CONFIGURED') {
            return [$canonical, $result, null];
        }
        $definition = $canonical['operational_definitions'][0] ?? null;
        if (! is_array($definition) || ! is_string($definition['preferred_term'] ?? null)
            || ! is_string($definition['observed_term'] ?? null)
            || ! is_array($definition['supporting_edge_ids'] ?? null)
            || count($definition['supporting_edge_ids']) < 1 || count($definition['supporting_edge_ids']) > 8) {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_NO_EVIDENCE_BOUND_TERMINOLOGY';

            return [$canonical, $result, null];
        }

        $byId = [];
        foreach ($canonical['relations'] as $edge) {
            $byId[$edge['id']] = $edge;
        }
        $path = [];
        foreach ($definition['supporting_edge_ids'] as $id) {
            if (! is_string($id) || ! isset($byId[$id])) {
                $result['semantic_reasoning']['status'] = 'ABSTAIN_INVALID_DEFINITION_EVIDENCE';

                return [$canonical, $result, null];
            }
            $path[] = $byId[$id];
        }
        $first = $path[0];
        $last = $path[count($path) - 1];
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
            'explanation' => 'Structural verification only; no AI inference.',
        ];
        if ($this->validator->read($canonical, $preflight)['status'] !== 'CANDIDATE_REVIEW_REQUIRED') {
            $result['semantic_reasoning']['status'] = 'ABSTAIN_INVALID_EVIDENCE_PATH';

            return [$canonical, $result, null];
        }

        $anchors = [];
        foreach ($path as $edge) {
            $p = $edge['provenance'];
            $anchors[] = [
                'edge_id' => $edge['id'],
                'locator' => mb_substr((string) $p['locator'], 0, 250),
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
        $raw = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $digest = strtoupper(hash('sha256', $raw));
        $result['context_sha256'] = $digest;
        $result['preview'] = [
            'status' => 'READY_FOR_EXPLICIT_CLOUD_APPROVAL',
            'sha256' => $digest,
            'context' => $context,
            'warning' => 'Only this metadata is sent to the selected API provider after user approval. Provider pricing, retention and terms apply.',
        ];

        return [$canonical, $result, $preflight];
    }
}
