<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCloudInferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cloud_gateway_requires_authentication(): void
    {
        Http::preventStrayRequests();
        $this->postJson('/api/ai-r69a/cloud-inference', [
            'provider' => 'gemini',
            'focus' => 'Telaah sumber resmi',
            'approved_focus' => true,
            'approved_public_evidence' => true,
        ])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_cloud_gateway_needs_both_per_request_approvals(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', [
            'provider' => 'gemini',
            'focus' => 'Telaah sumber resmi',
            'approved_focus' => true,
            'approved_public_evidence' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('approved_public_evidence');
        Http::assertNothingSent();
    }

    public function test_cloud_gateway_rejects_unknown_provider(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', [
            'provider' => 'arbitrary',
            'focus' => 'Telaah sumber resmi',
            'approved_focus' => true,
            'approved_public_evidence' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('provider');
        Http::assertNothingSent();
    }
}
