<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class R89SemanticRegulatoryAnalysisTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PREVIEW = '/api/qa-superior-r89/semantic-preview';
    private const ANALYZE = '/api/qa-superior-r89/semantic-candidates';

    private function requestBody(int $year = 2026): array
    {
        return [
            'year' => $year,
            'as_of' => $year === 2027 ? '2027-01-01' : '2026-11-21',
            'code' => '2.11.11.2.01.0004',
        ];
    }

    private function login(string $role = 'reviewer'): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG', 'role' => $role]));
    }

    private function consent(string $provider, string $sha, int $year = 2026): array
    {
        return array_merge($this->requestBody($year), [
            'provider' => $provider,
            'consent' => true,
            'approved_cloud_metadata' => true,
            'approved_provider_charges' => true,
            'approved_context_sha256' => $sha,
        ]);
    }

    private function configure(string $provider, string $model): string
    {
        $key = 'test-credentials-for-'.$provider.'-0001234567890';
        app(AiProviderCredentialVault::class)->save('ID-DIY-YOG', $provider, $key, $model);

        return $key;
    }

    private function reply(string $relation = 'RELATED', string $confidence = 'MEDIUM'): string
    {
        return json_encode([
            'semantic_relation' => $relation,
            'semantic_confidence' => $confidence,
            'explanation' => 'Istilah berkaitan, namun indikator dan dasar hukumnya belum diperiksa.',
        ], JSON_THROW_ON_ERROR);
    }

    private function digest(): string
    {
        return (string) $this->getJson(self::PREVIEW.'?'.http_build_query($this->requestBody()))
            ->assertOk()->assertJsonPath('preview.status', 'READY_FOR_EXPLICIT_CLOUD_APPROVAL')
            ->json('preview.sha256');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('cache.default','array');
        // Multiple requests in one test exercise consent/guards, not the rate limiter.
        // The production route retains throttle:2,1 unchanged.
        $this->withoutMiddleware(ThrottleRequests::class);
        // Fake-provider tests explicitly enable cloud; product default is OFF.
        config()->set('pi.semantic_cloud.enabled', true);
        Http::preventStrayRequests();
    }

    public function test_cloud_preview_and_inference_require_login_and_same_tenant(): void
    {
        $this->getJson(self::PREVIEW.'?'.http_build_query($this->requestBody()))->assertUnauthorized();
        $this->postJson(self::ANALYZE, $this->consent('deepseek', str_repeat('A', 64)))
            ->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER']))
            ->getJson(self::PREVIEW.'?'.http_build_query($this->requestBody()))->assertForbidden();
        $this->postJson(self::ANALYZE, $this->consent('deepseek', str_repeat('A', 64)))
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_preview_never_calls_provider_and_discloses_exact_bounded_metadata(): void
    {
        $this->login();
        $result = $this->getJson(self::PREVIEW.'?'.http_build_query($this->requestBody()))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema', 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1')
            ->assertJsonPath('preview.status', 'READY_FOR_EXPLICIT_CLOUD_APPROVAL')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'NOT_RUN')
            ->assertJsonPath('source_rights', 'EXPLICIT_APPROVAL_REQUIRED_BEFORE_CLOUD_TRANSFER');
        $context = $result->json('preview.context');
        $this->assertSame('DOCUMENTARY_TERMINOLOGY_ONLY_NO_PRIMARY_PROVISION_TEXT', $context['scope']);
        $this->assertCount(2, $context['evidence']);
        $this->assertArrayNotHasKey('tenant_id', $context);
        $this->assertArrayNotHasKey('api_key', $context);
        $this->assertArrayNotHasKey('document_bytes', $context);
        $this->assertMatchesRegularExpression('/\A[A-F0-9]{64}\z/', $result->json('preview.sha256'));
        Http::assertNothingSent();
    }

    public function test_no_consent_no_charges_approval_or_missing_preview_blocks_cloud_request(): void
    {
        $this->login();
        $sha = $this->digest();
        $this->configure('deepseek', 'deepseek-flash');
        $payload = $this->consent('deepseek', $sha);
        $payload['approved_provider_charges'] = false;
        $this->postJson(self::ANALYZE, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('approved_provider_charges');

        $payload = $this->consent('deepseek', $sha);
        $payload['approved_cloud_metadata'] = false;
        $this->postJson(self::ANALYZE, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('approved_cloud_metadata');
        Http::assertNothingSent();
    }

    public function test_unconfigured_cloud_provider_never_makes_network_request(): void
    {
        $this->login();
        $sha = $this->digest();
        $this->postJson(self::ANALYZE, $this->consent('openai', $sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_PROVIDER_NOT_CONFIGURED')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'NOT_RUN');
        Http::assertNothingSent();
    }

    public function test_2027_abstains_before_any_remote_inference(): void
    {
        $this->login();
        $this->configure('deepseek', 'deepseek-flash');
        $preview = $this->getJson(self::PREVIEW.'?'.http_build_query($this->requestBody(2027)))
            ->assertOk()->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_NO_VERSIONED_CASE')
            ->assertJsonPath('preview.status', 'ABSTAIN_CONTEXT_NOT_READY');
        $this->postJson(self::ANALYZE, $this->consent('deepseek', str_repeat('0', 64), 2027))
            ->assertOk()->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_NO_VERSIONED_CASE');
        Http::assertNothingSent();
    }

    public function test_modified_preview_digest_is_rejected_without_outbound_request(): void
    {
        $this->login();
        $this->configure('deepseek', 'deepseek-flash');
        $this->postJson(self::ANALYZE, $this->consent('deepseek', str_repeat('0', 64)))
            ->assertStatus(409)->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_PREVIEW_STALE');
        Http::assertNothingSent();
    }

    public function test_deepseek_credentials_from_existing_vault_are_used_with_approved_context_only(): void
    {
        $this->login();
        $sha = $this->digest();
        $key = $this->configure('deepseek', 'deepseek-flash');
        Http::fake(['https://api.deepseek.com/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $this->reply()], 'finish_reason' => 'stop']],
        ], 200)]);
        $res = $this->postJson(self::ANALYZE, $this->consent('deepseek', $sha))
            ->assertOk()->assertJsonPath('provider', 'deepseek')
            ->assertJsonPath('model', 'deepseek-flash')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'CLOUD_PROVIDER_CALLED')
            ->assertJsonPath('semantic_reasoning.status', 'CANDIDATE_REVIEW_REQUIRED')
            ->assertJsonPath('semantic_reasoning.candidates.0.semantic_relation', 'RELATED')
            ->assertJsonPath('semantic_reasoning.candidates.0.supporting_relation_ids', ['E05','E12'])
            ->assertJsonPath('semantic_reasoning.candidates.0.equivalence_asserted', false)
            ->assertJsonPath('semantic_reasoning.legal_validation_status', 'NOT_ASSESSED')
            ->assertJsonPath('semantic_reasoning.gates.production_admission', 'DENIED');
        $this->assertStringNotContainsString($key, $res->getContent());
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($key): bool {
            $data = $request->data();
            $ctx = json_decode($data['messages'][1]['content'], true);
            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer '.$key)
                && $data['model'] === 'deepseek-flash'
                && ! isset($data['tools'])
                && ! isset($ctx['tenant_id'], $ctx['api_key'], $ctx['document_bytes'])
                && ($ctx['scope'] ?? '') === 'DOCUMENTARY_TERMINOLOGY_ONLY_NO_PRIMARY_PROVISION_TEXT'
                && count($ctx['evidence'] ?? []) === 2;
        });
    }

    public function test_openai_cloud_model_is_user_configured_and_cannot_approve_equivalence(): void
    {
        $this->login();
        $sha = $this->digest();
        $this->configure('openai', 'gpt-4.1-mini');
        Http::fake(['https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $this->reply('EQUIVALENT', 'HIGH')], 'finish_reason' => 'stop']],
        ], 200)]);
        $this->postJson(self::ANALYZE, $this->consent('openai', $sha))->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('semantic_reasoning.status', 'CANDIDATE_REVIEW_REQUIRED')
            ->assertJsonPath('semantic_reasoning.candidates.0.equivalence_asserted', false)
            ->assertJsonPath('semantic_reasoning.gates.equivalence_asserted', false)
            ->assertJsonPath('semantic_reasoning.gates.numeric_comparison', 'BLOCKED');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && ($request->data()['max_completion_tokens'] ?? null) === 400
            && ! isset($request->data()['temperature'], $request->data()['max_tokens']));
    }

    public function test_gemini_cloud_endpoint_preserves_strict_evidence_contract(): void
    {
        $this->login();
        $sha = $this->digest();
        $this->configure('gemini', 'gemini-3.5-flash-lite');
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $this->reply('RELATED')]]], 'finishReason' => 'STOP']],
        ], 200)]);
        $this->postJson(self::ANALYZE, $this->consent('gemini', $sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'CANDIDATE_REVIEW_REQUIRED');
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent');
        Http::assertSentCount(1);
    }

    public function test_provider_failure_truncation_or_hallucinated_relation_abstains(): void
    {
        $this->login();
        $sha=$this->digest();
        $this->configure('deepseek','deepseek-flash');
        $this->configure('groq','test-model');
        $this->configure('openai','gpt-4.1-mini');
        Http::fake([
            'https://api.deepseek.com/*'=>Http::response(['error'=>['message'=>'private provider body']],402),
            'https://api.groq.com/*'=>Http::response(['choices'=>[['message'=>['content'=>$this->reply()],'finish_reason'=>'length']]],200),
            'https://api.openai.com/*'=>Http::response(['choices'=>[['message'=>['content'=>$this->reply('LEGAL_COMPLIANCE_APPROVED')],'finish_reason'=>'stop']]],200),
        ]);
        $this->postJson(self::ANALYZE,$this->consent('deepseek',$sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.status','ABSTAIN_CLOUD_PROVIDER_UNAVAILABLE')
            ->assertJsonPath('semantic_reasoning.provider_execution','CLOUD_PROVIDER_ATTEMPTED')
            ->assertJsonPath('provider_recovery.failure_class','PROVIDER_BALANCE');
        $this->postJson(self::ANALYZE,$this->consent('deepseek',$sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.provider_execution','NOT_RUN')
            ->assertJsonPath('provider_recovery.automatic_retry',false);
        Http::assertSentCount(1);
        $this->postJson(self::ANALYZE,$this->consent('groq',$sha))->assertOk()
            ->assertJsonPath('provider_recovery.failure_class','PROVIDER_OUTPUT_INVALID');
        $this->postJson(self::ANALYZE,$this->consent('openai',$sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.status','ABSTAIN_INVALID_SEMANTIC_PROPOSAL')
            ->assertJsonPath('semantic_reasoning.candidates',[])
            ->assertJsonPath('semantic_reasoning.gates.relation_promotion','NOT_PERFORMED');
        Http::assertSentCount(3);
    }

    public function test_rate_limit_suppresses_repeat_but_preview_and_other_provider_remain_available(): void
    {
        $this->login();
        $sha=$this->digest();
        $this->configure('deepseek','deepseek-flash');
        $this->configure('gemini','gemini-3.5-flash-lite');
        Http::fake([
            'https://api.deepseek.com/*'=>Http::response([],429,['Retry-After'=>'300']),
            'https://generativelanguage.googleapis.com/*'=>Http::response(['candidates'=>[['content'=>['parts'=>[['text'=>$this->reply()]]],'finishReason'=>'STOP']]],200),
        ]);
        $first=$this->postJson(self::ANALYZE,$this->consent('deepseek',$sha))->assertOk()
            ->assertJsonPath('provider_recovery.failure_class','PROVIDER_RATE_LIMIT');
        self::assertGreaterThanOrEqual(time()+295,$first->json('provider_recovery.retry_not_before'));
        $this->postJson(self::ANALYZE,$this->consent('deepseek',$sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.provider_execution','NOT_RUN');
        self::assertSame($sha,$this->digest());
        Http::assertSentCount(1);
        $this->postJson(self::ANALYZE,$this->consent('gemini',$sha))->assertOk()
            ->assertJsonPath('semantic_reasoning.provider_execution','CLOUD_PROVIDER_CALLED');
        Http::assertSentCount(2);
    }

    public function test_admin_can_globally_disable_cloud_calls(): void
    {
        $this->login();
        $sha = $this->digest();
        $this->configure('deepseek', 'deepseek-flash');
        config()->set('pi.semantic_cloud.enabled', false);
        $this->postJson(self::ANALYZE, $this->consent('deepseek', $sha))
            ->assertOk()->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_CLOUD_DISABLED_BY_ADMIN');
        Http::assertNothingSent();
    }

    public function test_missing_canonical_evidence_fails_closed_before_provider(): void
    {
        $this->login();
        $this->configure('deepseek', 'deepseek-flash');
        config()->set('pi.development_case.registry_path', storage_path('r89-missing.json'));
        $this->postJson(self::ANALYZE, $this->consent('deepseek', str_repeat('0',64)))
            ->assertStatus(503)->assertJsonPath('code', 'R89_CANONICAL_SOURCE_UNAVAILABLE');
        Http::assertNothingSent();
    }
}
