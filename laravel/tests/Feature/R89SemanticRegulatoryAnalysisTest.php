<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class R89SemanticRegulatoryAnalysisTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-superior-r89/semantic-candidates';

    private function requestBody(int $year = 2026): array
    {
        return [
            'consent' => true,
            'year' => $year,
            'as_of' => $year === 2027 ? '2027-01-01' : '2026-11-21',
            'code' => '2.11.11.2.01.0004',
        ];
    }

    private function login(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
    }

    private function enableLocal(): void
    {
        config()->set('pi.semantic_local.enabled', true);
        config()->set('pi.semantic_local.model', 'gemma3:1b');
    }

    private function response(string $relation = 'RELATED', string $confidence = 'MEDIUM'): array
    {
        return [
            'message' => ['content' => json_encode([
                'semantic_relation' => $relation,
                'semantic_confidence' => $confidence,
                'explanation' => 'Kedua istilah berhubungan, namun cakupan dan indikator belum diverifikasi.',
            ], JSON_THROW_ON_ERROR)],
            'done' => true,
        ];
    }

    public function test_requires_login_and_matching_tenant_and_explicit_consent(): void
    {
        Http::preventStrayRequests();
        $this->postJson(self::URL, $this->requestBody())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'FOREIGN']))
            ->postJson(self::URL, $this->requestBody())->assertForbidden();

        $this->login();
        $input = $this->requestBody();
        $input['consent'] = false;
        $this->postJson(self::URL, $input)->assertUnprocessable()
            ->assertJsonValidationErrors('consent');
        Http::assertNothingSent();
    }

    public function test_provider_is_disabled_by_default_with_no_hidden_call(): void
    {
        Http::preventStrayRequests();
        $this->login();
        $this->postJson(self::URL, $this->requestBody())->assertOk()
            ->assertJsonPath('schema', 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1')
            ->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_PROVIDER_NOT_CONFIGURED')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'NOT_RUN')
            ->assertJsonPath('source_rights', 'LOCAL_ONLY_NO_CLOUD_TRANSFER')
            ->assertJsonPath('semantic_reasoning.gates.equivalence_asserted', false);
        Http::assertNothingSent();
    }

    public function test_2027_stays_abstained_even_if_model_enabled(): void
    {
        Http::preventStrayRequests();
        $this->login();
        $this->enableLocal();
        $this->postJson(self::URL, $this->requestBody(2027))->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_NO_VERSIONED_CASE')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'NOT_RUN');
        Http::assertNothingSent();
    }

    public function test_local_model_receives_only_bounded_terms_and_documentary_anchors(): void
    {
        $this->login();
        $this->enableLocal();
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::response($this->response(), 200),
        ]);
        $this->postJson(self::URL, $this->requestBody())->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'CANDIDATE_REVIEW_REQUIRED')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'OLLAMA_LOCAL_CALLED')
            ->assertJsonPath('semantic_reasoning.candidates.0.semantic_relation', 'RELATED')
            ->assertJsonPath('semantic_reasoning.candidates.0.supporting_relation_ids', ['E05','E12'])
            ->assertJsonPath('semantic_reasoning.candidates.0.source_node_id', 'AFFAIR-WASTE')
            ->assertJsonPath('semantic_reasoning.candidates.0.target_node_id', 'IND-WASTE-COMMUNITY')
            ->assertJsonPath('semantic_reasoning.candidates.0.legal_validation_status', 'NOT_ASSESSED')
            ->assertJsonPath('semantic_reasoning.gates.relation_promotion', 'NOT_PERFORMED')
            ->assertJsonPath('semantic_reasoning.gates.equivalence_asserted', false)
            ->assertJsonPath('semantic_reasoning.gates.production_admission', 'DENIED');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $context = json_decode($payload['messages'][1]['content'], true, 8);
            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $payload['model'] === 'gemma3:1b'
                && $payload['stream'] === false
                && $payload['format'] === 'json'
                && ($context['scope'] ?? '') === 'DOCUMENTARY_TERMINOLOGY_ONLY_NO_PRIMARY_PROVISION_TEXT'
                && count($context['evidence'] ?? []) === 2
                && ! isset($context['tenant_id'], $context['api_key']);
        });
    }

    public function test_even_model_equivalence_cannot_be_admitted(): void
    {
        $this->login();
        $this->enableLocal();
        Http::preventStrayRequests();
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response($this->response('EQUIVALENT', 'HIGH'), 200)]);
        $this->postJson(self::URL, $this->requestBody())->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'CANDIDATE_REVIEW_REQUIRED')
            ->assertJsonPath('semantic_reasoning.semantic_confidence', 'HIGH')
            ->assertJsonPath('semantic_reasoning.candidates.0.equivalence_asserted', false)
            ->assertJsonPath('semantic_reasoning.gates.equivalence_asserted', false)
            ->assertJsonPath('semantic_reasoning.legal_validation_status', 'NOT_ASSESSED');
        Http::assertSentCount(1);
    }

    public function test_provider_hallucinated_relation_fails_closed(): void
    {
        $this->login();
        $this->enableLocal();
        Http::preventStrayRequests();
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response($this->response('LEGAL_COMPLIANCE_APPROVED'), 200)]);
        $this->postJson(self::URL, $this->requestBody())->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_INVALID_SEMANTIC_PROPOSAL')
            ->assertJsonPath('semantic_reasoning.candidates', [])
            ->assertJsonPath('semantic_reasoning.gates.production_admission', 'DENIED');
    }

    public function test_unavailable_provider_and_truncated_answer_abstain(): void
    {
        $this->login();
        $this->enableLocal();
        Http::preventStrayRequests();
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([], 503)]);
        $this->postJson(self::URL, $this->requestBody())->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_LOCAL_PROVIDER_UNAVAILABLE')
            ->assertJsonPath('semantic_reasoning.provider_execution', 'OLLAMA_LOCAL_ATTEMPTED');

        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'message' => ['content' => json_encode([
                'semantic_relation' => 'RELATED',
                'semantic_confidence' => 'HIGH',
                'explanation' => 'Belum dapat disimpulkan.',
            ], JSON_THROW_ON_ERROR)], 'done' => false,
        ], 200)]);
        $this->postJson(self::URL, $this->requestBody())->assertOk()
            ->assertJsonPath('semantic_reasoning.status', 'ABSTAIN_LOCAL_PROVIDER_UNAVAILABLE');
    }

    public function test_missing_source_does_not_call_provider(): void
    {
        $this->login();
        $this->enableLocal();
        Http::preventStrayRequests();
        config()->set('pi.development_case.registry_path', storage_path('r89-source-does-not-exist.json'));
        $this->postJson(self::URL, $this->requestBody())->assertStatus(503)
            ->assertJsonPath('code', 'R89_CANONICAL_SOURCE_UNAVAILABLE');
        Http::assertNothingSent();
    }

    public function test_invalid_year_and_code_are_rejected_without_provider_call(): void
    {
        $this->login();
        $this->enableLocal();
        Http::preventStrayRequests();
        $input = $this->requestBody();
        $input['year'] = 1800;
        $input['code'] = '../../secrets';
        $this->postJson(self::URL, $input)->assertUnprocessable()
            ->assertJsonValidationErrors(['year','code']);
        Http::assertNothingSent();
    }
}
