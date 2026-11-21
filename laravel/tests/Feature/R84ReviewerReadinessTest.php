<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Regulatory\R84EvidenceFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class R84ReviewerReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_fingerprint_matches_r84_store_encoding_and_rejects_unreviewed_contract(): void
    {
        $candidate = [
            'target_provision_id' => 'UU-18-2008:PASAL-25',
            'article' => 25,
            'paragraphs_requested' => [3, 4],
            'excerpt' => 'Warga mengolah sampah – menjaga lingkungan.',
            'review_status' => 'REVIEW_REQUIRED',
            'relation_admission' => 'NOT_PERFORMED',
        ];
        $fingerprint = app(R84EvidenceFingerprint::class);
        $expected = strtoupper(hash('sha256', json_encode($candidate,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));

        $this->assertSame($expected, $fingerprint->candidate($candidate));
        $this->assertMatchesRegularExpression('/\A[A-F0-9]{64}\z/', $expected);

        $candidate['relation_admission'] = 'COMPLETED';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('R84_FINGERPRINT_INPUT_REJECTED');
        $fingerprint->candidate($candidate);
    }

    public function test_unmigrated_r84_ledger_fails_closed_for_get_and_write(): void
    {
        // PHPUnit uses in-memory SQLite; no operator-owned database is modified.
        $reviewer = User::factory()->create([
            'tenant_id' => 'ID-DIY-YOG', 'role' => 'reviewer',
        ]);
        Schema::dropIfExists('regulatory_evidence_reviews');

        $this->actingAs($reviewer)->getJson('/api/qa-regulatory-r84/evidence-reviews')
            ->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN')
            ->assertJsonPath('code', 'R84_DATABASE_MIGRATION_PENDING')
            ->assertJsonPath('production_admission', 'DENIED');

        $this->actingAs($reviewer)->postJson('/api/qa-regulatory-r84/evidence-reviews', [
            'candidate_id' => 'UU-18-2008:PASAL-16',
            'review_dimension' => 'CITATION_ACCURACY',
            'decision' => 'REVISION_REQUIRED',
            'reason' => 'Sumber belum dapat divalidasi pada versi ini.',
            'source_manifest_sha256' => str_repeat('A', 64),
            'candidate_sha256' => str_repeat('B', 64),
        ])->assertStatus(503)
          ->assertJsonPath('status', 'ABSTAIN')
          ->assertJsonPath('code', 'R84_DATABASE_MIGRATION_PENDING')
          ->assertJsonPath('relation_promotion', 'DENIED');

        $this->assertFalse(Schema::hasTable('regulatory_evidence_reviews'));
    }

    public function test_migrated_r84_read_model_advertises_storage_readiness_but_no_legal_admission(): void
    {
        $reviewer = User::factory()->create(['tenant_id' => 'ID-DIY-YOG', 'role' => 'reviewer']);
        $this->actingAs($reviewer)->getJson(
            '/api/qa-regulatory-r84/evidence-reviews?candidate_id=UU-18-2008:PASAL-16'
        )->assertOk()
         ->assertJsonPath('review_storage_state', 'READY')
         ->assertJsonPath('relation_promotion', 'NOT_PERFORMED')
         ->assertJsonPath('legal_harmonization', 'NOT_AUTOMATED')
         ->assertJsonPath('production_admission', 'DENIED');
    }
}
