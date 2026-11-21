<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RegulatoryPlanningCrosswalkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-superior-r88/planning-crosswalk';

    public function test_crosswalk_preserves_evidence_and_blocks_unsupported_equivalence(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER']))->getJson(self::URL)->assertForbidden();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $r = $this->getJson(self::URL)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema', 'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1')
            ->assertJsonPath('classification.status', 'EXACT_LOCAL_OBSERVATION')
            ->assertJsonPath('continuity.status', 'ABSTAIN_MISSING_COMPARISON_VERSION')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.review_decision', 'NO_ACTIVE_DECISION');
        $this->assertNotEmpty($r->json('relations'));
        foreach ($r->json('relations') as $edge) {
            $this->assertNotEmpty($edge['provenance']['locator']);
            $this->assertSame('NOT_ASSESSED', $edge['legal_applicability']);
        }
        $this->assertCount(10, $r->json('lifecycle.stages'));
        $this->assertCount(5, $r->json('specialized_views'));
        $this->getJson(self::URL.'?year=2027')->assertOk()
            ->assertJsonPath('result_count', 0)->assertJsonCount(0, 'relations');
        $this->getJson(self::URL.'?as_of=2020-01-01')->assertOk()
            ->assertJsonPath('result_count', 0)->assertJsonCount(0, 'relations')->assertJsonCount(0, 'operational_definitions')->assertJsonCount(0, 'hierarchy');
        $this->getJson(self::URL.'?code=2.11.99')->assertOk()
            ->assertJsonPath('binding.status', 'ABSTAIN_UNBOUND_CODE');
        $this->getJson(self::URL.'?as_of=invalid')->assertUnprocessable();
    }

    public function test_missing_case_provenance_fails_closed_without_partial_output(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        config()->set('pi.development_case.registry_path', storage_path('missing-r88-source.json'));
        $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('status', 'ABSTAIN')
            ->assertJsonMissingPath('relations');
    }
}
