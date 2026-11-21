<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RegulatoryEvidenceTest extends TestCase
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

    public function test_only_authenticated_tenant_can_open_a_verified_source_excerpt(): void
    {
        $hash = $this->configureMinimalSource();
        $url = '/api/qa-regulatory-r70h/evidence/BASE_2026/'.$hash;

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson($url)->assertForbidden();

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson($url)->assertOk()
            ->assertJsonPath('schema', 'PI_R70H_VERIFIED_EVIDENCE_LOOKUP_V1')
            ->assertJsonPath('matches.0.excerpt', 'Pasal 1: definisi pengujian.')
            ->assertJsonPath('matches.0.pdf_page', 2)
            ->assertJsonPath('review_status', 'REVIEW_REQUIRED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED');

        $this->getJson('/api/qa-regulatory-r70h/evidence/BASE_2026/'.str_repeat('f', 64))
            ->assertNotFound();

        // The evidence endpoint must fail closed if either authoritative
        // QA-derived JSON changes after the scope/ledger hash was recorded.
        file_put_contents($this->tempFiles[0], '{"tampered":true}');
        $this->getJson($url)->assertStatus(503)
            ->assertJsonPath('code', 'REGULATORY_EVIDENCE_UNAVAILABLE');
    }

    private function configureMinimalSource(): string
    {
        $sourceHash = strtoupper(str_repeat('a', 64));
        $excerpt = 'Pasal 1: definisi pengujian.';
        $excerptHash = strtoupper(hash('sha256', $excerpt));
        $ledger = [
            'schema' => 'PI_R70C_AMENDMENT_LEDGER_QA_V1',
            'tenant_id' => 'ID-DIY-YOG', 'pilot' => 'QA',
            'numeric_comparison' => 'BLOCKED', 'legal_harmonization' => 'NOT_ASSESSED',
            'full_annex_version_reconciliation' => 'NOT_PERFORMED',
            'reviewer_gate' => 'REQUIRED', 'production_admission' => 'DENIED',
            'official_sources' => 1, 'identity_verified' => 1,
            'amendment_count' => 0, 'targets_evidenced' => 0, 'metadata_anomalies' => 0,
            'source_chain' => [[
                'id' => 'BASE_2026', 'sequence' => 0, 'title' => 'Regulasi Uji',
                'document_role' => 'BASE', 'pdf_sha256' => $sourceHash,
                'explicit_targets' => [], 'metadata_warning' => null,
                'evidence' => [[
                    'source_id' => 'BASE_2026', 'pdf_sha256' => $sourceHash,
                    'pdf_page' => 2, 'excerpt' => $excerpt,
                    'excerpt_sha256' => $excerptHash, 'evidence_type' => 'SOURCE_EXCERPT',
                ]],
            ]],
        ];
        $ledgerJson = json_encode($ledger, JSON_THROW_ON_ERROR);
        $scope = [
            'schema' => 'PI_R70D_OPERATIVE_SCOPE_QA_V1',
            'source_ledger_schema' => $ledger['schema'],
            'source_ledger_sha256' => strtoupper(hash('sha256', $ledgerJson)),
            'tenant_id' => 'ID-DIY-YOG', 'pilot' => 'QA',
            'numeric_comparison' => 'BLOCKED', 'legal_harmonization' => 'NOT_ASSESSED',
            'annex_content_diff' => 'NOT_PERFORMED',
            'reviewer_gate' => 'REQUIRED', 'production_admission' => 'DENIED',
            'source_count' => 1, 'amendment_count' => 0,
            'amendments_with_both_annex_targets' => 0, 'explicit_target_clauses' => 0,
            'timeline' => [[
                'id' => 'BASE_2026', 'sequence' => 0, 'targets' => [],
                'dates' => [], 'metadata_warning' => null, 'status' => 'REVIEW_REQUIRED',
            ]],
        ];
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pi-r70h-'.bin2hex(random_bytes(8));
        $ledgerPath = $root.'-ledger.json';
        $scopePath = $root.'-scope.json';
        file_put_contents($ledgerPath, $ledgerJson);
        file_put_contents($scopePath, json_encode($scope, JSON_THROW_ON_ERROR));
        $this->tempFiles = [$ledgerPath, $scopePath];
        config()->set('pi.regulatory.ledger_path', $ledgerPath);
        config()->set('pi.regulatory.scope_path', $scopePath);

        return $excerptHash;
    }
}
