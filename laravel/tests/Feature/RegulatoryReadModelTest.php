<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RegulatoryReadModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_tenant_receives_laravel_native_regulatory_model(): void
    {
        config()->set('pi.regulatory.ledger_path', 'D:/Research/PlanningIntelligence/PI_DEVELOPMENT_ARTIFACTS/PI_R69E_SUPERIOR_SPECIALIZED_LAUNCHER_QA_20261104/_qa_regulatory_r70c/amendment_ledger.json');
        config()->set('pi.regulatory.scope_path', 'D:/Research/PlanningIntelligence/PI_DEVELOPMENT_ARTIFACTS/PI_R69E_SUPERIOR_SPECIALIZED_LAUNCHER_QA_20261104/_qa_regulatory_r70d/operative_scope.json');
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);

        $response = $this->actingAs($user)->getJson('/api/qa-regulatory-r70g/laravel-read-model');

        $response->assertOk()
            ->assertJsonPath('schema', 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1')
            ->assertJsonPath('summary.source_count', 7)
            ->assertJsonPath('summary.amendment_count', 5)
            ->assertJsonPath('summary.scope_targets_first_95_pages', 10)
            ->assertJsonPath('summary.evidence_excerpt_sha256_verified', 47)
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.production_admission', 'DENIED')
            ->assertJsonCount(7, 'documents');
    }

    public function test_returns_401_when_regulatory_model_is_requested_without_login(): void
    {
        $this->getJson('/api/qa-regulatory-r70g/laravel-read-model')->assertUnauthorized();
    }
}
