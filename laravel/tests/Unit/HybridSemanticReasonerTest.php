<?php

namespace Tests\Unit;

use App\Services\Pi\Development\HybridSemanticReasoner;
use PHPUnit\Framework\TestCase;

final class HybridSemanticReasonerTest extends TestCase
{
    private function crosswalk(): array
    {
        return [
            'schema' => 'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1',
            'tenant_id' => 'ID-DIY-YOG',
            'filters' => [
                'year' => 2026, 'as_of' => '2026-11-21', 'code' => '2.11.11.2.01.0004',
            ],
            'result_count' => 1,
            'classification' => ['status' => 'EXACT_LOCAL_OBSERVATION'],
            'continuity' => ['equivalence_asserted' => false],
            'nodes' => [['id' => 'A'], ['id' => 'B'], ['id' => 'C']],
            'relations' => [
                ['id' => 'E01', 'from' => 'A', 'to' => 'B', 'provenance' => [
                    'locator' => 'Observed R85 edge A-B',
                    'source_url' => './overview-r69c.json',
                    'source_sha256' => null,
                    'verification_status' => 'LOCAL_QA_OBSERVATION_NOT_SOURCE_PINNED',
                    'as_of_date' => '2026-11-18',
                ]],
                ['id' => 'E02', 'from' => 'B', 'to' => 'C', 'provenance' => [
                    'locator' => 'Observed R85 edge B-C',
                    'source_url' => 'https://peraturan.go.id/',
                    'source_sha256' => str_repeat('A', 64),
                    'verification_status' => 'VERIFIED_SOURCE_BYTES',
                    'as_of_date' => '2026-11-18',
                ]],
            ],
            'interpretation' => [
                'legal_harmonization' => 'NOT_ASSESSED',
                'numeric_comparison' => 'BLOCKED',
                'review_decision' => 'NO_ACTIVE_DECISION',
                'production_admission' => 'DENIED',
            ],
        ];
    }

    private function proposal(): array
    {
        return [
            'model_id' => 'qa-local-model-v1',
            'year' => 2026,
            'as_of' => '2026-11-21',
            'code' => '2.11.11.2.01.0004',
            'source_node_id' => 'A',
            'target_node_id' => 'B',
            'supporting_relation_ids' => ['E01'],
            'semantic_relation' => 'RELATED',
            'semantic_confidence' => 'HIGH',
            'explanation' => 'Hubungan istilah hanya hipotesis dan memerlukan penelaahan sumber asli.',
        ];
    }

    public function test_no_provider_never_invokes_a_model_or_promotes_relations(): void
    {
        $result = (new HybridSemanticReasoner)->read($this->crosswalk());
        $this->assertSame('ABSTAIN_PROVIDER_NOT_CONFIGURED', $result['status']);
        $this->assertSame('NOT_RUN', $result['provider_execution']);
        $this->assertSame([], $result['candidates']);
        $this->assertFalse($result['gates']['equivalence_asserted']);
        $this->assertSame('NOT_PERFORMED', $result['gates']['relation_promotion']);
        $this->assertSame('BLOCKED', $result['gates']['numeric_comparison']);
        $this->assertSame('DENIED', $result['gates']['production_admission']);
    }

    public function test_evidence_path_can_only_produce_review_candidate(): void
    {
        $result = (new HybridSemanticReasoner)->read($this->crosswalk(), $this->proposal());
        $this->assertSame('CANDIDATE_REVIEW_REQUIRED', $result['status']);
        $this->assertSame('HIGH', $result['semantic_confidence']);
        $this->assertSame('PARTIAL_OR_UNPINNED_SOURCE', $result['evidence_confidence']);
        $this->assertSame('NOT_ASSESSED', $result['legal_validation_status']);
        $this->assertFalse($result['semantic_confidence_is_probability']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame(['E01'], $result['candidates'][0]['supporting_relation_ids']);
        $this->assertFalse($result['candidates'][0]['equivalence_asserted']);
    }

    public function test_equivalence_proposal_is_not_verified_equivalence(): void
    {
        $proposal = $this->proposal();
        $proposal['semantic_relation'] = 'EQUIVALENT';
        $result = (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal);
        $this->assertSame('CANDIDATE_REVIEW_REQUIRED', $result['status']);
        $this->assertFalse($result['gates']['equivalence_asserted']);
        $this->assertFalse($result['candidates'][0]['equivalence_asserted']);
        $this->assertSame('NOT_ASSESSED', $result['candidates'][0]['legal_validation_status']);
    }

    public function test_unknown_node_and_reversed_evidence_path_abstain(): void
    {
        $proposal = $this->proposal();
        $proposal['target_node_id'] = 'INVENTED';
        $this->assertSame('ABSTAIN_UNKNOWN_EVIDENCE_NODE',
            (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal)['status']);

        $proposal = $this->proposal();
        $proposal['source_node_id'] = 'B';
        $proposal['target_node_id'] = 'A';
        $this->assertSame('ABSTAIN_UNSUPPORTED_GRAPH_PATH',
            (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal)['status']);
    }

    public function test_unanchored_relation_and_model_context_drift_abstain(): void
    {
        $proposal = $this->proposal();
        $proposal['supporting_relation_ids'] = ['E99'];
        $this->assertSame('ABSTAIN_UNSUPPORTED_GRAPH_PATH',
            (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal)['status']);

        $proposal = $this->proposal();
        $proposal['year'] = 2027;
        $this->assertSame('ABSTAIN_INVALID_SEMANTIC_PROPOSAL',
            (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal)['status']);

        $proposal = $this->proposal();
        $proposal['semantic_relation'] = 'LEGAL_COMPLIANCE_CONFIRMED';
        $this->assertSame('ABSTAIN_INVALID_SEMANTIC_PROPOSAL',
            (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal)['status']);
    }

    public function test_unverified_2027_scope_abstains_even_with_proposal(): void
    {
        $model = $this->crosswalk();
        $model['filters']['year'] = 2027;
        $model['result_count'] = 0;
        $model['classification']['status'] = 'YEAR_SCOPE_REVIEW_REQUIRED';
        $result = (new HybridSemanticReasoner)->read($model, $this->proposal());
        $this->assertSame('ABSTAIN_NO_VERSIONED_CASE', $result['status']);
        $this->assertSame('NOT_RUN', $result['provider_execution']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_canonical_legal_and_tenant_gates_cannot_be_bypassed(): void
    {
        $model = $this->crosswalk();
        $model['interpretation']['numeric_comparison'] = 'ALLOWED';
        $this->assertSame('ABSTAIN_CANONICAL_GUARDS',
            (new HybridSemanticReasoner)->read($model, $this->proposal())['status']);

        $model = $this->crosswalk();
        $model['tenant_id'] = 'OTHER';
        $this->assertSame('ABSTAIN_CANONICAL_GUARDS',
            (new HybridSemanticReasoner)->read($model, $this->proposal())['status']);
    }

    public function test_pin_strength_and_model_confidence_are_separate(): void
    {
        $model = $this->crosswalk();
        $model['relations'][0]['provenance']['source_sha256'] = str_repeat('B', 64);
        $model['relations'][0]['provenance']['verification_status'] = 'VERIFIED_SOURCE_BYTES';
        $result = (new HybridSemanticReasoner)->read($model, $this->proposal());
        $this->assertSame('PINNED_SOURCE_BYTES', $result['evidence_confidence']);
        $this->assertSame('HIGH', $result['semantic_confidence']);
        $this->assertSame('NOT_ASSESSED', $result['legal_validation_status']);
    }

    public function test_insufficient_semantics_abstains_without_candidate(): void
    {
        $proposal = $this->proposal();
        $proposal['semantic_relation'] = 'INSUFFICIENT';
        $result = (new HybridSemanticReasoner)->read($this->crosswalk(), $proposal);
        $this->assertSame('ABSTAIN_SEMANTIC_INSUFFICIENT', $result['status']);
        $this->assertSame([], $result['candidates']);
    }
}
