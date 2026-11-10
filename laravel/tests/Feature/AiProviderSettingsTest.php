<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiProviderSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_tenant_administrators_can_view_provider_settings(): void
    {
        Storage::fake('local');
        $this->getJson('/api/ai-r69a/provider-settings')->assertUnauthorized();

        $viewer = User::factory()->create(['role' => 'reviewer']);
        $this->actingAs($viewer)->getJson('/api/ai-r69a/provider-settings')
            ->assertForbidden()->assertJsonPath('code', 'AI_SETTINGS_ADMIN_REQUIRED');

        $other = User::factory()->create(['role' => 'institution_admin', 'tenant_id' => 'QA-OTHER-TENANT']);
        $this->actingAs($other)->getJson('/api/ai-r69a/provider-settings')
            ->assertForbidden()->assertJsonPath('code', 'TENANT_DENIED');
    }

    public function test_admin_can_save_encrypted_credentials_without_exposing_secrets(): void
    {
        Storage::fake('local');
        $user = User::factory()->create([
            'role' => 'institution_admin',
            'tenant_id' => 'ID-DIY-YOG',
            'password' => 'test-admin-password-123',
        ]);
        $key = 'test-GEMINI-key-1234567890';

        $response = $this->actingAs($user)->putJson('/api/ai-r69a/provider-settings/gemini', [
            'model' => 'gemini-3.5-flash-lite',
            'api_key' => $key,
            'password' => 'test-admin-password-123',
        ]);
        $response->assertOk()
            ->assertJsonPath('schema', 'PI_AI_PROVIDER_SETTINGS_V1')
            ->assertJsonPath('providers.0.configured', true)
            ->assertJsonPath('providers.0.source', 'encrypted_vault')
            ->assertJsonPath('providers.0.stored_config', true)
            ->assertJsonPath('api_keys_returned', false);
        $this->assertStringNotContainsString($key, $response->getContent());

        $files = Storage::disk('local')->allFiles('pi-ai');
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString($key, Storage::disk('local')->get($files[0]));
        $this->assertSame($key, app(AiProviderCredentialVault::class)
            ->resolve('ID-DIY-YOG', 'gemini')['api_key']);

        $this->actingAs($user)->getJson('/api/ai-r69a/status')
            ->assertOk()->assertJsonPath('providers.1.model', 'gemini-3.5-flash-lite')
            ->assertJsonPath('providers.1.configured', true);
    }

    public function test_password_is_required_to_modify_or_delete_credentials(): void
    {
        Storage::fake('local');
        $user = User::factory()->create([
            'role' => 'superadmin',
            'password' => 'test-admin-password-123',
        ]);

        $this->actingAs($user)->putJson('/api/ai-r69a/provider-settings/gemini', [
            'model' => 'gemini-3.5-flash-lite',
            'api_key' => 'test-GEMINI-key-1234567890',
            'password' => 'wrong-password',
        ])->assertForbidden()->assertJsonPath('code', 'ADMIN_PASSWORD_INVALID');
        $this->assertSame([], Storage::disk('local')->allFiles('pi-ai'));

        $this->actingAs($user)->putJson('/api/ai-r69a/provider-settings/gemini', [
            'model' => 'gemini-3.5-flash-lite',
            'api_key' => 'test-GEMINI-key-1234567890',
            'password' => 'test-admin-password-123',
        ])->assertOk();

        $this->actingAs($user)->deleteJson('/api/ai-r69a/provider-settings/gemini', [
            'password' => 'wrong-password',
        ])->assertForbidden();
        $this->assertTrue(app(AiProviderCredentialVault::class)
            ->resolve('ID-DIY-YOG', 'gemini')['stored_key']);

        $this->actingAs($user)->deleteJson('/api/ai-r69a/provider-settings/gemini', [
            'password' => 'test-admin-password-123',
        ])->assertOk()->assertJsonPath('providers.0.stored_config', false);
        $this->assertFalse(app(AiProviderCredentialVault::class)
            ->resolve('ID-DIY-YOG', 'gemini')['stored_key']);
    }

    public function test_encrypted_key_is_used_by_guarded_cloud_inference_without_leaking(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $user = User::factory()->create([
            'role' => 'institution_admin',
            'password' => 'test-admin-password-123',
        ]);
        $key = 'test-GEMINI-key-1234567890';
        $this->actingAs($user)->putJson('/api/ai-r69a/provider-settings/gemini', [
            'model' => 'gemini-3.5-flash-lite',
            'api_key' => $key,
            'password' => 'test-admin-password-123',
        ])->assertOk();

        $document = $this->actingAs($user)->getJson('/api/ai-r69a/evidence-context')
            ->assertOk()->json('documents.0');
        $this->assertNotEmpty($document['evidence_anchors']);
        $anchor = $document['evidence_anchors'][0];
        $ref = [
            'source_id' => $document['source_id'],
            'pdf_page' => $anchor['pdf_page'],
            'excerpt_sha256' => $anchor['excerpt_sha256'],
        ];
        $reply = json_encode([
            'status' => 'REVIEW_REQUIRED',
            'summary' => 'Draf awal memerlukan verifikasi reviewer.',
            'uncertainties' => ['Belum memeriksa teks norma penuh.'],
            'questions_for_reviewer' => ['Apakah sumber ini masih berlaku?'],
            'evidence_refs' => [$ref],
        ]);
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => $reply]]]],
            ],
        ], 200)]);

        $response = $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', [
            'provider' => 'gemini',
            'focus' => 'Telaah perubahan regulasi',
            'approved_focus' => true,
            'approved_public_evidence' => true,
        ]);
        $response->assertOk()->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('model', 'gemini-3.5-flash-lite')
            ->assertJsonPath('brief.status', 'REVIEW_REQUIRED')
            ->assertJsonPath('human_review', 'REQUIRED');
        $this->assertStringNotContainsString($key, $response->getContent());
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', $key));
        Http::assertSentCount(1);
    }
}
