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
            ->assertJsonPath('public_excerpt_mode', 'METADATA_ONLY')
            ->assertJsonPath('public_excerpt_count', 0)
            ->assertJsonPath('human_review', 'REQUIRED');
        $this->assertStringNotContainsString($key, $response->getContent());
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', $key)
            && ! str_contains($request->data()['contents'][0]['parts'][0]['text'],
                'approved_public_excerpt_snippets'));
        Http::assertSentCount(1);
    }

    public function test_public_excerpts_are_sent_only_with_separate_explicit_opt_in(): void
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

        $previewResponse = $this->actingAs($user)->getJson(
            '/api/ai-r69a/evidence-context?public_excerpt_preview=1&focus=perubahan%20lampiran'
        )->assertOk();
        $preview = $previewResponse->json('public_excerpt_preview');
        $digest = $previewResponse->json('public_excerpt_preview_sha256');
        $this->assertNotEmpty($preview);
        $first = $preview[0];
        $ref = [
            'source_id' => $first['source_id'],
            'pdf_page' => $first['pdf_page'],
            'excerpt_sha256' => $first['excerpt_sha256'],
        ];
        $reply = json_encode([
            'status' => 'REVIEW_REQUIRED',
            'summary' => 'Kutipan pada halaman bukti membutuhkan review perubahan lampiran.',
            'uncertainties' => ['Kutipan pendek tidak cukup untuk keputusan hukum.'],
            'questions_for_reviewer' => ['Apakah perubahan lampiran konsisten dengan dokumen utuh?'],
            'evidence_refs' => [$ref],
        ]);
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $reply]]]]],
        ], 200)]);

        $staleRequest = [
            'provider' => 'gemini',
            'focus' => 'perubahan lampiran',
            'approved_focus' => true,
            'approved_public_evidence' => true,
            'approved_public_excerpts' => true,
            'approved_public_excerpts_sha256' => str_repeat('0', 64),
        ];
        $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', $staleRequest)
            ->assertStatus(409)->assertJsonPath('code', 'PUBLIC_EXCERPT_PREVIEW_STALE');
        Http::assertNothingSent();

        $staleRequest['approved_public_excerpts_sha256'] = $digest;
        $response = $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', $staleRequest);
        $response->assertOk()
            ->assertJsonPath('public_excerpt_mode', 'EXPLICIT_OPT_IN')
            ->assertJsonPath('human_review', 'REQUIRED')
            ->assertJsonPath('legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED');
        $this->assertGreaterThan(0, $response->json('public_excerpt_count'));
        $this->assertStringNotContainsString($key, $response->getContent());
        Http::assertSent(function ($request) use ($first, $key) {
            $sent = $request->data();
            $input = json_decode($sent['contents'][0]['parts'][0]['text'], true);
            $excerpts = $input['approved_public_excerpt_snippets'] ?? [];
            return $request->hasHeader('x-goog-api-key', $key)
                && ! empty($excerpts)
                && ! isset($input['tenant_id'])
                && ! isset($input['pdf_content'])
                && ($excerpts[0]['source_id'] ?? null) === $first['source_id']
                && ($excerpts[0]['excerpt_sha256'] ?? null) === $first['excerpt_sha256']
                && ($excerpts[0]['excerpt_text'] ?? '') !== '';
        });
        Http::assertSentCount(1);
    }

    public function test_malformed_model_json_fails_closed_with_specific_code(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $user = User::factory()->create([
            'role' => 'institution_admin',
            'password' => 'test-admin-password-123',
        ]);
        $this->actingAs($user)->putJson('/api/ai-r69a/provider-settings/gemini', [
            'model' => 'gemini-3.5-flash-lite',
            'api_key' => 'test-GEMINI-key-1234567890',
            'password' => 'test-admin-password-123',
        ])->assertOk();

        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => [
                'parts' => [['text' => '{"status":"REVIEW_REQUIRED",']],
            ]]],
        ], 200)]);

        $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', [
            'provider' => 'gemini',
            'focus' => 'Telaah perubahan regulasi',
            'approved_focus' => true,
            'approved_public_evidence' => true,
        ])->assertStatus(502)->assertJsonPath('code', 'CLOUD_OUTPUT_INVALID_JSON');
        Http::assertSentCount(1);
    }

    public function test_token_truncation_is_rejected_before_json_parsing(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $user = User::factory()->create([
            'role' => 'institution_admin',
            'password' => 'test-admin-password-123',
        ]);
        $this->actingAs($user)->putJson('/api/ai-r69a/provider-settings/gemini', [
            'model' => 'gemini-3.5-flash-lite',
            'api_key' => 'test-GEMINI-key-1234567890',
            'password' => 'test-admin-password-123',
        ])->assertOk();

        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => [
                'parts' => [['text' => '{"status":']],
            ]]],
        ], 200)]);

        $this->actingAs($user)->postJson('/api/ai-r69a/cloud-inference', [
            'provider' => 'gemini',
            'focus' => 'Telaah perubahan regulasi',
            'approved_focus' => true,
            'approved_public_evidence' => true,
        ])->assertStatus(502)->assertJsonPath('code', 'CLOUD_OUTPUT_TRUNCATED');
        Http::assertSentCount(1);
    }
}
