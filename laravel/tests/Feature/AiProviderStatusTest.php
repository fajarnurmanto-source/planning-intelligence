<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiProviderStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_provider_status_requires_authenticated_tenant(): void
    {
        $this->getJson('/api/ai-r69a/status')->assertUnauthorized();
    }

    public function test_provider_status_is_read_only_and_does_not_expose_secrets(): void
    {
        $user = User::factory()->create(['tenant_id' => 'ID-DIY-YOG']);

        $response = $this->actingAs($user)->getJson('/api/ai-r69a/status');
        $response->assertOk()
            ->assertJsonPath('schema', 'PI_R69A_AI_PROVIDER_STATUS_V1')
            ->assertJsonPath('cloud_permission', 'EXPLICIT_PER_REQUEST')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED')
            ->assertJsonPath('api_keys_returned', false)
            ->assertJsonCount(7, 'providers')
            ->assertJsonCount(5, 'cloud_providers');
        $this->assertArrayNotHasKey('api_key', $response->json('providers.0'));
    }
}
