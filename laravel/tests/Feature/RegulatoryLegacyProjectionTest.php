<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RegulatoryLegacyProjectionTest extends TestCase
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

    public function test_contracts_require_authenticated_correct_tenant(): void
    {
        $this->configureSources();
        foreach (['/api/qa-regulatory-r70c/ledger', '/api/qa-regulatory-r70d/scope'] as $url) {
            $this->getJson($url)->assertUnauthorized();
            $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
                ->getJson($url)->assertForbidden();
            auth()->logout();
        }
    }

    public function test_verified_source_pair_is_exposed_read_only_without_inference(): void
    {
        $this->configureSources();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $ledger = $this->getJson('/api/qa-regulatory-r70c/ledger')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R70C_AMENDMENT_LEDGER_QA_V1')
            ->assertJsonPath('source_chain.0.id', 'BASE_2026')
            ->assertJsonPath('source_chain.0.evidence.0.excerpt', 'Pasal 1: definisi pengujian.')
            ->assertJsonPath('production_admission', 'DENIED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED');
        $this->assertStringContainsString('no-store', $ledger->headers->get('Cache-Control') ?? '');

        $scope = $this->getJson('/api/qa-regulatory-r70d/scope')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R70D_OPERATIVE_SCOPE_QA_V1')
            ->assertJsonPath('timeline.0.id', 'BASE_2026')
            ->assertJsonPath('source_count', 1)
            ->assertJsonPath('legal_harmonization', 'NOT_ASSESSED');
        $this->assertStringContainsString('no-store', $scope->headers->get('Cache-Control') ?? '');
    }

    public function test_tampered_or_missing_snapshots_fail_closed_for_both_legacy_contracts(): void
    {
        $this->configureSources();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        // A valid file that was mutated after the pinned scope hash was
        // generated must not be returned, even on the ledger endpoint.
        file_put_contents($this->tempFiles[0], '{"schema":"PI_R70C_AMENDMENT_LEDGER_QA_V1","tampered":true}');
        foreach (['/api/qa-regulatory-r70c/ledger', '/api/qa-regulatory-r70d/scope'] as $url) {
            $this->getJson($url)->assertStatus(503)
                ->assertJsonPath('status', 'ABSTAIN')
                ->assertJsonPath('code', 'REGULATORY_LEGACY_PROJECTION_UNAVAILABLE')
                ->assertDontSee('tampered');
        }
        config()->set('pi.regulatory.ledger_path', 'does-not-exist.json');
        $this->getJson('/api/qa-regulatory-r70c/ledger')->assertStatus(503);
    }

    private function configureSources(): void
    {
        $pdfHash = strtoupper(str_repeat('a', 64));
        $excerpt = 'Pasal 1: definisi pengujian.';
        $ledger = [
            'schema' => 'PI_R70C_AMENDMENT_LEDGER_QA_V1',
            'tenant_id' => 'ID-DIY-YOG', 'pilot' => 'QA',
            'numeric_comparison' => 'BLOCKED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'full_annex_version_reconciliation' => 'NOT_PERFORMED',
            'reviewer_gate' => 'REQUIRED', 'production_admission' => 'DENIED',
            'official_sources' => 1, 'identity_verified' => 1,
            'amendment_count' => 0, 'targets_evidenced' => 0,
            'metadata_anomalies' => 0,
            'source_chain' => [[
                'id' => 'BASE_2026', 'sequence' => 0, 'title' => 'Regulasi Uji',
                'document_role' => 'BASE', 'pdf_sha256' => $pdfHash,
                'explicit_targets' => [], 'metadata_warning' => null,
                'evidence' => [[
                    'source_id' => 'BASE_2026', 'pdf_sha256' => $pdfHash,
                    'pdf_page' => 2, 'excerpt' => $excerpt,
                    'excerpt_sha256' => strtoupper(hash('sha256', $excerpt)),
                    'evidence_type' => 'SOURCE_EXCERPT',
                ]],
            ]],
        ];
        $ledgerJson = json_encode($ledger, JSON_THROW_ON_ERROR);
        $scope = [
            'schema' => 'PI_R70D_OPERATIVE_SCOPE_QA_V1',
            'source_ledger_schema' => $ledger['schema'],
            'source_ledger_sha256' => strtoupper(hash('sha256', $ledgerJson)),
            'tenant_id' => 'ID-DIY-YOG', 'pilot' => 'QA',
            'numeric_comparison' => 'BLOCKED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'annex_content_diff' => 'NOT_PERFORMED',
            'reviewer_gate' => 'REQUIRED', 'production_admission' => 'DENIED',
            'source_count' => 1, 'amendment_count' => 0,
            'amendments_with_both_annex_targets' => 0, 'explicit_target_clauses' => 0,
            'timeline' => [[
                'id' => 'BASE_2026', 'sequence' => 0,
                'targets' => [], 'dates' => [], 'metadata_warning' => null,
                'status' => 'REVIEW_REQUIRED',
            ]],
        ];
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pi-r77-'.bin2hex(random_bytes(8));
        $ledgerPath = $root.'-ledger.json';
        $scopePath = $root.'-scope.json';
        file_put_contents($ledgerPath, $ledgerJson);
        file_put_contents($scopePath, json_encode($scope, JSON_THROW_ON_ERROR));
        $this->tempFiles = [$ledgerPath, $scopePath];
        config()->set('pi.regulatory.ledger_path', $ledgerPath);
        config()->set('pi.regulatory.scope_path', $scopePath);
    }
}
