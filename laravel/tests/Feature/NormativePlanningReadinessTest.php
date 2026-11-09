<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class NormativePlanningReadinessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-planning-r71/normative-readiness';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pi.regulatory_auto_ancestry.enabled', true);
        config()->set('pi.regulatory_auto_ancestry.cache_store', 'array');
        Cache::store('array')->forget(OfficialNormativeAncestry::CACHE_KEY);
    }

    public function test_cross_module_readiness_is_authenticated_tenant_scoped_and_abstains(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson(self::URL)->assertForbidden();

        $graph = [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'tenant_id' => 'ID-DIY-YOG',
            'source' => 'JDIH_BPK_OFFICIAL_METADATA',
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'nodes' => [
                ['id' => 'UU-39-2008', 'official_resolution' => 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE'],
                ['id' => 'UUD-1945', 'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED'],
            ],
            'edges' => [['from' => 'UU-39-2008', 'to' => 'UUD-1945']],
        ];
        Cache::store('array')->put(OfficialNormativeAncestry::CACHE_KEY, $graph, now()->addHour());

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema', 'PI_R71_NORMATIVE_PLANNING_READINESS_V1')
            ->assertJsonPath('normative_node_count', 2)
            ->assertJsonPath('normative_citation_count', 1)
            ->assertJsonPath('unresolved_normative_identity_count', 1)
            ->assertJsonPath('crosswalk_status', 'NO_VERIFIED_PROVISION_TO_PLANNING_EDGE')
            ->assertJsonPath('readiness', 'ABSTAIN')
            ->assertJsonPath('legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED')
            ->assertJsonPath('candidate_edge_count', 1)
            ->assertJsonPath('candidate_provision_to_planning_edges.0.provision_locator', 'Pasal 2 ayat (4) huruf a')
            ->assertJsonPath('candidate_provision_to_planning_edges.0.evidence_status', 'METADATA_LOCATOR_ONLY_PDF_NOT_PINNED')
            ->assertJsonPath('candidate_provision_to_planning_edges.0.review_status', 'REVIEW_REQUIRED')
            ->assertJsonPath('candidate_provision_to_planning_edges.0.legal_effect', 'NOT_ASSESSED')
            ->assertJsonCount(2, 'documents')
            ->assertJsonCount(0, 'verified_provision_to_planning_edges');
    }

    public function test_missing_or_cross_tenant_ancestry_never_invents_a_crosswalk(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('code', 'NORMATIVE_PLANNING_EVIDENCE_UNAVAILABLE');

        Cache::store('array')->put(OfficialNormativeAncestry::CACHE_KEY, [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'tenant_id' => 'QA-OTHER',
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'nodes' => [],
            'edges' => [],
        ], now()->addHour());
        $this->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN');
    }
}
