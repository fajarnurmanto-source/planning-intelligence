<?php

namespace Tests\Feature;

use App\Models\RegulatoryEvidenceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class R84EvidenceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_authenticated_tenant_scoped_and_never_promotes_relations(): void
    {
        $this->getJson('/api/qa-regulatory-r84/evidence-reviews')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson('/api/qa-regulatory-r84/evidence-reviews')->assertForbidden();

        $reviewer = User::factory()->create([
            'tenant_id' => 'ID-DIY-YOG',
            'role' => 'reviewer',
        ]);
        RegulatoryEvidenceReview::query()->create([
            'tenant_id' => 'ID-DIY-YOG',
            'candidate_id' => 'UU-18-2008:PASAL-16',
            'review_dimension' => 'CITATION_ACCURACY',
            'decision' => 'ACCEPTED',
            'reason' => 'Kutipan dan halaman telah dibandingkan dengan PDF asli.',
            'source_manifest_sha256' => str_repeat('A', 64),
            'candidate_sha256' => str_repeat('B', 64),
            'reviewer_id' => $reviewer->id,
            'decided_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->getJson('/api/qa-regulatory-r84/evidence-reviews?candidate_id=UU-18-2008:PASAL-16')
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.reviewer.username', $reviewer->username)
            ->assertJsonPath('relation_promotion', 'NOT_PERFORMED')
            ->assertJsonPath('legal_harmonization', 'NOT_AUTOMATED')
            ->assertJsonPath('production_admission', 'DENIED');

        $this->getJson('/api/qa-regulatory-r84/evidence-reviews?candidate_id=../../.env')->assertNotFound();
    }

    public function test_store_requires_reviewer_role_and_fails_closed_without_verified_r82_sources(): void
    {
        $payload = [
            'candidate_id' => 'UU-18-2008:PASAL-16',
            'review_dimension' => 'CITATION_ACCURACY',
            'decision' => 'ACCEPTED',
            'reason' => 'Kutipan dan halaman telah dibandingkan dengan PDF asli.',
            'source_manifest_sha256' => str_repeat('A', 64),
            'candidate_sha256' => str_repeat('B', 64),
        ];

        $viewer = User::factory()->create(['tenant_id' => 'ID-DIY-YOG', 'role' => 'viewer']);
        $this->actingAs($viewer)->postJson('/api/qa-regulatory-r84/evidence-reviews', $payload)
            ->assertForbidden();

        $reviewer = User::factory()->create(['tenant_id' => 'ID-DIY-YOG', 'role' => 'reviewer']);
        $this->actingAs($reviewer)->postJson('/api/qa-regulatory-r84/evidence-reviews', $payload)
            ->assertStatus(503)
            ->assertJsonPath('relation_promotion', 'DENIED')
            ->assertJsonPath('production_admission', 'DENIED');

        $this->assertDatabaseCount('regulatory_evidence_reviews', 0);
    }
}
