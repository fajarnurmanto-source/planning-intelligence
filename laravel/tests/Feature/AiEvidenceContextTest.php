<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiEvidenceContextTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_evidence_context_requires_authenticated_tenant(): void
    {
        $this->getJson('/api/ai-r69a/evidence-context')->assertUnauthorized();
    }

    public function test_evidence_context_exposes_verified_anchors_without_provider_calls(): void
    {
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $response = $this->actingAs($user)->getJson('/api/ai-r69a/evidence-context');

        $response->assertOk()
            ->assertJsonPath('schema', 'PI_R69A_LARAVEL_AI_EVIDENCE_CONTEXT_V1')
            ->assertJsonPath('source_schema', 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1')
            ->assertJsonPath('ai_provider_called', false)
            ->assertJsonPath('context_only', true)
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED');

        $documents = $response->json('documents');
        $this->assertNotEmpty($documents);
        $this->assertArrayHasKey('evidence_anchors', $documents[0]);
        $this->assertArrayNotHasKey('api_key', $response->json());
        $this->assertStringNotContainsString('"excerpt_text"', $response->getContent());
    }

    public function test_public_excerpt_preview_is_explicit_hash_grounded_and_provider_free(): void
    {
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $this->actingAs($user)->getJson('/api/ai-r69a/evidence-context?public_excerpt_preview=1')
            ->assertUnprocessable();

        $preview = $this->actingAs($user)->getJson(
            '/api/ai-r69a/evidence-context?public_excerpt_preview=1&focus=perubahan%20lampiran'
        )->assertOk()->assertJsonPath('preview_focus', 'perubahan lampiran')
            ->assertJsonPath('ai_provider_called', false)
            ->assertJsonPath('preview_provider_called', false)
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED');

        $excerpts = $preview->json('public_excerpt_preview');
        $this->assertNotEmpty($excerpts);
        $this->assertLessThanOrEqual(21, count($excerpts));
        $this->assertSame(
            strtoupper(hash('sha256', json_encode($excerpts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))),
            $preview->json('public_excerpt_preview_sha256')
        );
        foreach ($excerpts as $excerpt) {
            $this->assertSame('REVIEW_REQUIRED', $excerpt['review_status']);
            $this->assertMatchesRegularExpression('/^[A-Fa-f0-9]{64}$/', $excerpt['excerpt_sha256']);
            $this->assertGreaterThan(0, $excerpt['pdf_page']);
            $this->assertSame(1, preg_match('/^.{1,320}$/us', $excerpt['excerpt_text']));
        }
        $this->assertArrayNotHasKey('excerpt', $preview->json('documents.0.evidence_anchors.0'));
        $this->assertStringNotContainsString('api_key', $preview->getContent());
    }
}
