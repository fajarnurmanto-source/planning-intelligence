<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RegulatorySnapshotTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $files = [];
    private ?string $root = null;

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if ($this->root !== null && is_dir($this->root)) {
            rmdir($this->root);
        }
        parent::tearDown();
    }

    public function test_source_listing_is_tenant_gated_and_does_not_claim_unverified_pdf(): void
    {
        $this->fixture();
        foreach (['/api/qa-regulatory-r70b/sources', '/api/qa-regulatory-r70b/pdf?source_id=BASE_2026'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson('/api/qa-regulatory-r70b/sources')->assertForbidden();
        auth()->logout();

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $this->getJson('/api/qa-regulatory-r70b/sources')->assertOk()
            ->assertJsonPath('schema', 'PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('verified', 0)
            ->assertJsonPath('sources.0.has_local_pdf', false)
            ->assertJsonPath('sources.0.status', 'ACQUIRED_NEEDS_REVIEW')
            ->assertJsonPath('production_admission', 'DENIED');
        $this->getJson('/api/qa-regulatory-r70b/pdf?source_id=BASE_2026')
            ->assertNotFound()->assertJsonPath('code', 'PDF_SNAPSHOT_NOT_AVAILABLE');
        $this->getJson('/api/qa-regulatory-r70b/pdf?source_id=../file.pdf')
            ->assertStatus(400);
    }

    public function test_hash_pinned_local_pdf_can_be_downloaded_only_by_authenticated_tenant(): void
    {
        $pdf = $this->fixture();
        $this->installManifest('BASE_2026.pdf', strtoupper(hash('sha256', $pdf)));
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));

        $this->getJson('/api/qa-regulatory-r70b/sources')->assertOk()
            ->assertJsonPath('verified', 1)
            ->assertJsonPath('sources.0.has_local_pdf', true)
            ->assertJsonPath('sources.0.identity', 'PDF_SHA256_MATCHES_LEDGER');

        $response = $this->get('/api/qa-regulatory-r70b/pdf?source_id=BASE_2026');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
        $this->assertSame($pdf, $response->getContent());
    }

    public function test_mismatched_pdf_or_manifest_escape_fails_closed(): void
    {
        $pdf = $this->fixture();
        $this->installManifest('BASE_2026.pdf', strtoupper(hash('sha256', $pdf)));
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        file_put_contents($this->root.'/BASE_2026.pdf', '%PDF-TAMPERED');
        $this->getJson('/api/qa-regulatory-r70b/sources')->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN');
        $this->getJson('/api/qa-regulatory-r70b/pdf?source_id=BASE_2026')->assertStatus(503);

        $this->installManifest('../BASE_2026.pdf', strtoupper(hash('sha256', $pdf)));
        $this->getJson('/api/qa-regulatory-r70b/sources')->assertStatus(503)
            ->assertJsonPath('code', 'REGULATORY_SNAPSHOT_CATALOG_UNAVAILABLE');
    }

    public function test_missing_snapshot_is_not_falsely_reported_as_available(): void
    {
        $pdf = $this->fixture();
        $this->installManifest('BASE_2026.pdf', strtoupper(hash('sha256', $pdf)));
        unlink($this->root.'/BASE_2026.pdf');
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $this->getJson('/api/qa-regulatory-r70b/sources')->assertOk()
            ->assertJsonPath('verified', 0)
            ->assertJsonPath('sources.0.has_local_pdf', false);
        $this->getJson('/api/qa-regulatory-r70b/pdf?source_id=BASE_2026')->assertNotFound();
    }

    private function fixture(): string
    {
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pi-r78-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $pdfHash = strtoupper(hash('sha256', $pdf));
        $excerpt = 'Pasal 1: definisi pengujian.';
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
                'document_role' => 'BASE', 'pdf_sha256' => $pdfHash,
                'source_url' => 'https://jdih.jogjakota.go.id/asset/naskah/base.pdf',
                'metadata_url' => null,
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
            'numeric_comparison' => 'BLOCKED', 'legal_harmonization' => 'NOT_ASSESSED',
            'annex_content_diff' => 'NOT_PERFORMED', 'reviewer_gate' => 'REQUIRED',
            'production_admission' => 'DENIED',
            'source_count' => 1, 'amendment_count' => 0,
            'amendments_with_both_annex_targets' => 0,
            'explicit_target_clauses' => 0,
            'timeline' => [[
                'id' => 'BASE_2026', 'sequence' => 0, 'targets' => [],
                'dates' => [], 'metadata_warning' => null, 'status' => 'REVIEW_REQUIRED',
            ]],
        ];

        $paths = [
            'ledger' => $this->root.'/ledger.json',
            'scope' => $this->root.'/scope.json',
            'pdf' => $this->root.'/BASE_2026.pdf',
            'manifest' => $this->root.'/index.json',
        ];
        file_put_contents($paths['ledger'], $ledgerJson);
        file_put_contents($paths['scope'], json_encode($scope, JSON_THROW_ON_ERROR));
        file_put_contents($paths['pdf'], $pdf);
        $this->files = array_values($paths);
        config()->set('pi.regulatory.ledger_path', $paths['ledger']);
        config()->set('pi.regulatory.scope_path', $paths['scope']);
        config()->set('pi.regulatory.pdf_root', null);
        config()->set('pi.regulatory.pdf_manifest_path', null);

        return $pdf;
    }

    private function installManifest(string $relative, string $sha256): void
    {
        file_put_contents($this->root.'/index.json', json_encode([
            'schema' => 'PI_R78_LOCAL_PDF_INDEX_V1',
            'tenant_id' => 'ID-DIY-YOG',
            'sources' => [
                'BASE_2026' => ['relative_path' => $relative, 'sha256' => $sha256],
            ],
        ], JSON_THROW_ON_ERROR));
        config()->set('pi.regulatory.pdf_root', $this->root);
        config()->set('pi.regulatory.pdf_manifest_path', $this->root.'/index.json');
    }
}
