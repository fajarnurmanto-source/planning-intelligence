<?php

namespace Tests\Feature;

use App\Models\RegulatoryEvidenceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * R93 integrated acceptance: real Laravel routes, DB only :memory:,
 * one authenticated session through R85/R86/R88/R89/R84 and sign-out.
 * No provider invocation, no reviewer writes and no original owner DB.
 *
 * Full CSRF/cookie semantics are additionally exercised by the separate
 * ephemeral HTTP server workflow (PHPUnit intentionally bypasses CSRF).
 */
final class R93IntegratedAuthModuleAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOGIN = '/api/auth/login';

    private const PASSWORD = 'R93-CI-Only-Password-Not-A-User-Secret!';

    public function test_one_login_reaches_all_read_models_then_logout_invalidates_access(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create([
            'username' => 'r93_ci_operator',
            'tenant_id' => 'ID-DIY-YOG',
            'role' => 'institution_admin',
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->getJson('/api/qa-superior-r85/development-case')->assertUnauthorized();
        $this->getJson('/api/qa-superior-r86/regulatory-lifecycle')->assertUnauthorized();
        $this->getJson('/api/qa-superior-r88/planning-crosswalk')->assertUnauthorized();
        $this->getJson('/api/qa-superior-r89/semantic-preview')->assertUnauthorized();
        $this->getJson('/api/qa-regulatory-r84/evidence-reviews')->assertUnauthorized();

        $this->postJson(self::LOGIN, [
            'username' => $user->username,
            'password' => self::PASSWORD,
        ])->assertOk()->assertJsonPath('status', 'AUTHENTICATED');

        $this->getJson('/api/auth/session')->assertOk()
            ->assertJsonPath('status', 'AUTHENTICATED')
            ->assertJsonPath('username', $user->username)
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('role', 'institution_admin');

        $this->getJson('/api/qa-superior-r85/development-case')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_READ_MODEL_V1')
            ->assertJsonPath('interpretation.review_decision', 'NO_ACTIVE_DECISION');

        $this->getJson('/api/qa-superior-r86/regulatory-lifecycle')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R86_REGULATORY_DEVELOPMENT_LIFECYCLE_V1')
            ->assertJsonPath('case.lifecycle.stage_count', 10)
            ->assertJsonPath('interpretation.production_admission', 'DENIED');

        $this->getJson('/api/qa-superior-r88/planning-crosswalk')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'NOT_RUN')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED');

        $filters = http_build_query([
            'year' => 2026,
            'as_of' => '2026-11-21',
            'code' => '2.11.11.2.01.0004',
        ]);
        $this->getJson('/api/qa-superior-r89/semantic-preview?'.$filters)
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'NOT_RUN')
            ->assertJsonPath('semantic_reasoning.gates.production_admission', 'DENIED');

        $this->postJson('/api/qa-superior-r89/semantic-candidates', [
            'provider' => 'deepseek',
            'year' => 2026,
            'as_of' => '2026-11-21',
            'code' => '2.11.11.2.01.0004',
            'consent' => false,
            'approved_cloud_metadata' => false,
            'approved_provider_charges' => false,
            'approved_context_sha256' => str_repeat('A', 64),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'consent', 'approved_cloud_metadata', 'approved_provider_charges',
        ]);

        $this->getJson('/api/qa-regulatory-r84/evidence-reviews')
            ->assertOk()
            ->assertJsonPath('relation_promotion', 'NOT_PERFORMED')
            ->assertJsonPath('production_admission', 'DENIED')
            ->assertJsonCount(0, 'history');

        $this->assertDatabaseCount('regulatory_evidence_reviews', 0);
        Http::assertNothingSent();

        $this->postJson('/api/auth/logout')->assertOk()
            ->assertJsonPath('status', 'SIGNED_OUT');
        $this->assertGuest();
        $this->getJson('/api/auth/session')->assertUnauthorized();
        $this->getJson('/api/qa-superior-r88/planning-crosswalk')->assertUnauthorized();
    }

    public function test_tenant_fence_and_viewer_reviewer_write_guard_remain_fail_closed(): void
    {
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create([
            'tenant_id' => 'WRONG-TENANT',
            'role' => 'institution_admin',
        ]));

        foreach ([
            '/api/auth/session',
            '/api/qa-superior-r85/development-case',
            '/api/qa-superior-r86/regulatory-lifecycle',
            '/api/qa-superior-r88/planning-crosswalk',
            '/api/qa-superior-r89/semantic-preview',
            '/api/qa-regulatory-r84/evidence-reviews',
        ] as $url) {
            $this->getJson($url)->assertForbidden();
        }

        $this->actingAs(User::factory()->create([
            'tenant_id' => 'ID-DIY-YOG',
            'role' => 'viewer',
        ]));

        $this->postJson('/api/qa-regulatory-r84/evidence-reviews', [
            'candidate_id' => 'UU-18-2008:PASAL-16',
            'review_dimension' => 'CITATION_ACCURACY',
            'decision' => 'ACCEPTED',
            'reason' => 'R93 synthetic attempted decision; must remain denied.',
            'source_manifest_sha256' => str_repeat('A', 64),
            'candidate_sha256' => str_repeat('B', 64),
        ])->assertForbidden();

        $this->assertDatabaseCount('regulatory_evidence_reviews', 0);
        Http::assertNothingSent();
    }
}
