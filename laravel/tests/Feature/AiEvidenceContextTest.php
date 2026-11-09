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
    }
}
