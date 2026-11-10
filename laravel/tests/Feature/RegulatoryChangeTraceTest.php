<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlanningIntelligence\RegulatoryChangeTraceProjector;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RegulatoryChangeTraceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_change_trace_requires_authenticated_tenant(): void
    {
        $this->getJson('/api/qa-regulatory-r72/change-trace')->assertUnauthorized();
    }

    public function test_structural_chronology_groups_hash_pinned_targets_without_legal_claims(): void
    {
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $response = $this->actingAs($user)
            ->getJson('/api/qa-regulatory-r72/change-trace')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R72_STRUCTURAL_AMENDMENT_TRACE_V1')
            ->assertJsonPath('summary.document_count', 7)
            ->assertJsonPath('summary.amending_instrument_count', 5)
            ->assertJsonPath('summary.distinct_target_count', 2)
            ->assertJsonPath('summary.structural_change_candidate_count', 10)
            ->assertJsonPath('review_status', 'CANDIDATE_TRACE_ONLY')
            ->assertJsonPath('legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('annex_content_diff', 'NOT_PERFORMED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED');

        $groups = $response->json('target_groups');
        $this->assertSame(['LAMPIRAN I', 'LAMPIRAN II'], array_column($groups, 'target'));
        foreach ($groups as $group) {
            $this->assertCount(5, $group['events']);
            $previous = null;
            foreach ($group['events'] as $event) {
                $this->assertSame('REVIEW_REQUIRED', $event['review_status']);
                $this->assertSame('SUCCESSIVE_TARGET_MENTIONS_ONLY', $event['structural_relation']);
                $this->assertNotEmpty($event['evidence_refs']);
                $this->assertSame($previous, $event['previous_target_source_id']);
                $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $event['source_pdf_sha256']);
                foreach ($event['evidence_refs'] as $anchor) {
                    $this->assertSame($event['source_id'], $anchor['source_id']);
                    $this->assertGreaterThan(0, $anchor['pdf_page']);
                    $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $anchor['excerpt_sha256']);
                }
                $previous = $event['source_id'];
            }
        }
        $this->assertStringNotContainsString('"excerpt":', $response->getContent());
    }

    public function test_tampered_or_unapproved_source_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(RegulatoryChangeTraceProjector::class)->build([
            'schema' => 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1',
            'interpretation' => [
                'reviewer_gate' => 'REQUIRED',
                'legal_harmonization' => 'APPROVED',
                'annex_content_diff' => 'NOT_PERFORMED',
                'numeric_comparison' => 'BLOCKED',
                'production_admission' => 'DENIED',
            ],
        ]);
    }
}
