<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RegulatoryLegalTraceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_legacy_trace_requires_login_and_matching_tenant(): void
    {
        $this->sourceFixture();
        $path = '/api/qa-regulatory-r70a/trace';
        $this->getJson($path)->assertUnauthorized();

        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson($path)->assertForbidden();
    }

    public function test_authenticated_laravel_trace_is_grounded_and_abstains_from_legal_findings(): void
    {
        $this->sourceFixture();
        $response = $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson('/api/qa-regulatory-r70a/trace')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R70A_REGULATORY_LEGAL_TRACE_QA_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('decision', 'ABSTAIN')
            ->assertJsonPath('projection_version', 'R79_LARAVEL_PARTIAL_LEGACY_COMPATIBILITY')
            ->assertJsonPath('legacy_feature_parity', 'PARTIAL_NOT_EQUIVALENT')
            ->assertJsonPath('legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED')
            ->assertJsonCount(5, 'relationships')
            ->assertJsonCount(0, 'amendment_timeline');

        $data = $response->json();
        $this->assertContains('UU-12-2011', array_column($data['nodes'], 'id'));
        $this->assertContains('BASE_2026', array_column($data['nodes'], 'id'));
        $this->assertContains('AMENDS', array_column($data['relationships'], 'kind'));
        $this->assertNotContains('CERTIFIED_HARMONIZATION', array_column($data['relationships'], 'status'));
        $this->assertCount(3, $data['findings']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
    }

    public function test_mismatched_source_chain_fails_closed(): void
    {
        $this->sourceFixture();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        file_put_contents($this->tempFiles[0], '{"tampered":true}');

        $this->getJson('/api/qa-regulatory-r70a/trace')
            ->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN')
            ->assertJsonPath('code', 'R79_LEGAL_TRACE_NOT_AVAILABLE')
            ->assertDontSee('tampered');
    }

    private function sourceFixture(): void
    {
        $pdfHash = strtoupper(str_repeat('a', 64));
        $excerpt = 'Pasal 1: catatan sumber sintetis.';
        $ledger = [
            'schema' => 'PI_R70C_AMENDMENT_LEDGER_QA_V1',
            'tenant_id' => 'ID-DIY-YOG', 'pilot' => 'QA',
            'numeric_comparison' => 'BLOCKED', 'legal_harmonization' => 'NOT_ASSESSED',
            'full_annex_version_reconciliation' => 'NOT_PERFORMED',
            'reviewer_gate' => 'REQUIRED', 'production_admission' => 'DENIED',
            'official_sources' => 1, 'identity_verified' => 1,
            'amendment_count' => 0, 'targets_evidenced' => 0, 'metadata_anomalies' => 0,
            'source_chain' => [[
                'id' => 'BASE_2026', 'sequence' => 0, 'title' => 'Sumber Sintetis',
                'document_role' => 'BASE', 'pdf_sha256' => $pdfHash,
                'source_url' => 'https://jdih.jogjakota.go.id/asset/naskah/base.pdf',
                'metadata_url' => null, 'explicit_targets' => [], 'metadata_warning' => null,
                'evidence' => [[
                    'source_id' => 'BASE_2026', 'pdf_sha256' => $pdfHash,
                    'pdf_page' => 2, 'excerpt' => $excerpt,
                    'excerpt_sha256' => strtoupper(hash('sha256', $excerpt)),
                    'evidence_type' => 'SOURCE_EXCERPT',
                ]],
            ]],
        ];
        $ledgerRaw = json_encode($ledger, JSON_THROW_ON_ERROR);
        $scope = [
            'schema' => 'PI_R70D_OPERATIVE_SCOPE_QA_V1',
            'source_ledger_schema' => $ledger['schema'],
            'source_ledger_sha256' => strtoupper(hash('sha256', $ledgerRaw)),
            'tenant_id' => 'ID-DIY-YOG', 'pilot' => 'QA',
            'numeric_comparison' => 'BLOCKED', 'legal_harmonization' => 'NOT_ASSESSED',
            'annex_content_diff' => 'NOT_PERFORMED',
            'reviewer_gate' => 'REQUIRED', 'production_admission' => 'DENIED',
            'source_count' => 1, 'amendment_count' => 0,
            'amendments_with_both_annex_targets' => 0,
            'explicit_target_clauses' => 0,
            'timeline' => [[
                'id' => 'BASE_2026', 'sequence' => 0,
                'targets' => [], 'dates' => [],
                'metadata_warning' => null, 'status' => 'REVIEW_REQUIRED',
            ]],
        ];

        $stem = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pi-r79-'.bin2hex(random_bytes(8));
        $this->tempFiles = [$stem.'-ledger.json', $stem.'-scope.json'];
        file_put_contents($this->tempFiles[0], $ledgerRaw);
        file_put_contents($this->tempFiles[1], json_encode($scope, JSON_THROW_ON_ERROR));
        config()->set('pi.regulatory.ledger_path', $this->tempFiles[0]);
        config()->set('pi.regulatory.scope_path', $this->tempFiles[1]);
    }
}
