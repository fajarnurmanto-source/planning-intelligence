<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiReviewBriefTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_review_brief_requires_authentication(): void
    {
        $this->getJson('/api/ai-r69a/review-brief')->assertUnauthorized();
    }

    public function test_review_brief_is_source_grounded_and_fail_closed(): void
    {
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $response = $this->actingAs($user)->getJson('/api/ai-r69a/review-brief');

        $response->assertOk()
            ->assertJsonPath('schema', 'PI_R69A_LARAVEL_REVIEW_BRIEF_V1')
            ->assertJsonPath('restrictions.ai_provider_called', false)
            ->assertJsonPath('restrictions.human_review', 'REQUIRED')
            ->assertJsonPath('restrictions.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('restrictions.production_admission', 'DENIED');
        $this->assertNotEmpty($response->json('sources'));
        $this->assertNotEmpty($response->json('sources.0.evidence_anchors'));
    }
}
