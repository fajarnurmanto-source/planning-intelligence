<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiLocalInferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_local_inference_requires_authenticated_tenant(): void
    {
        Http::preventStrayRequests();
        $this->postJson('/api/ai-r69a/local-inference', [
            'provider' => 'ollama', 'consent' => true,
        ])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_local_inference_rejects_missing_consent_without_provider_request(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $this->actingAs($user)->postJson('/api/ai-r69a/local-inference', [
            'provider' => 'ollama', 'consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('consent');
        Http::assertNothingSent();
    }

    public function test_local_inference_rejects_cloud_provider_even_with_consent(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);
        $this->actingAs($user)->postJson('/api/ai-r69a/local-inference', [
            'provider' => 'gemini', 'consent' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('provider');
        Http::assertNothingSent();
    }
}
