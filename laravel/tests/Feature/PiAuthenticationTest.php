<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PiAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create(['username' => 'reviewer', 'password' => 'valid-password-123']);

        $this->postJson('/api/auth/login', [
            'username' => 'reviewer',
            'password' => 'wrong-password-123',
        ])->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_authenticates_user_and_exposes_free_entitlements(): void
    {
        $user = User::factory()->create([
            'username' => 'reviewer',
            'tenant_id' => 'ID-DIY-YOG',
            'role' => 'reviewer',
            'password' => 'valid-password-123',
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'reviewer',
            'password' => 'valid-password-123',
        ])->assertOk()->assertJsonPath('user.role', 'reviewer');

        $this->actingAs($user)->getJson('/api/qa-subscription-r69e/entitlements')
            ->assertOk()
            ->assertJsonPath('plan', 'FREE')
            ->assertJsonPath('modules.0', 'regulatory')
            ->assertJsonPath('production_admission', 'DENIED');
    }

    public function test_returns_403_for_user_from_another_tenant(): void
    {
        $user = User::factory()->create(['tenant_id' => 'QA-ISOLATION-OTHER']);

        $this->actingAs($user)->getJson('/api/qa-subscription-r69e/entitlements')
            ->assertForbidden()
            ->assertJsonPath('code', 'TENANT_DENIED');
    }
}
