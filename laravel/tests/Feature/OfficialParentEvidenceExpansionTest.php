<?php

namespace Tests\Feature;

use App\Services\Pi\Regulatory\OfficialParentEvidenceExpansion;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OfficialParentEvidenceExpansionTest extends TestCase
{
    private function graph(): array
    {
        return [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'nodes' => [
                ['id' => 'UU-39-2008', 'kind' => 'UU', 'number' => 39, 'year' => 2008,
                    'official_resolution' => 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE',
                    'metadata_url' => 'https://peraturan.bpk.go.id/Details/39719/uu-no-39-tahun-2008'],
                ['id' => 'UUD-1945', 'kind' => 'UUD', 'number' => null, 'year' => 1945,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED',
                    'metadata_url' => null],
                ['id' => 'UU-23-2014', 'kind' => 'UU', 'number' => 23, 'year' => 2014,
                    'official_resolution' => 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE',
                    'metadata_url' => 'https://peraturan.bpk.go.id/Details/38685/uu-no-23-tahun-2014'],
            ],
            'edges' => [],
            'unresolved_parent_count' => 1,
            'exhaustiveness' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    public function test_official_parent_abstracts_add_source_bound_constitution_and_amendment_candidates(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'peraturan.bpk.go.id/Details/39719/*' => Http::response(<<<'HTML'
<html><body>
<h1>Undang-undang (UU) No. 39 Tahun 2008 tentang Kementerian Negara</h1>
<h3>METADATA PERATURAN</h3>
<h3><span>STATUS</span> <span>PERATURAN</span></h3><p><span>Diubah</span> <span>dengan:</span></p><ol>
<li><a href="/Details/123456">UU No. 61 Tahun 2024</a> tentang Perubahan atas UU No. 39 Tahun 2008.</li>
<li><a href="https://peraturan.bpk.go.id.evil.example/Details/9999/uu-no-66-tahun-2024">UU No. 66 Tahun 2024</a> tentang tautan palsu</li>
</ol>
<h3>ABSTRAK PERATURAN</h3><ul>
<li>Pasal 4, Pasal 17, Pasal 20, dan Pasal 21 Undang-Undang Dasar Negara Republik Indonesia Tahun 1945.</li>
</ul></body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'peraturan.bpk.go.id/Details/38685/*' => Http::response(<<<'HTML'
<html><body>
<h1>Undang-undang (UU) Nomor 23 Tahun 2014 Pemerintahan Daerah</h1>
<h3>METADATA PERATURAN</h3>
<h3>STATUS PERATURAN</h3>
<p>Dicabut sebagian dengan:</p><ol><li><a href="/Details/123451/uu-no-1-tahun-2022">UU No. 1 Tahun 2022</a></li></ol>
<p>Diubah dengan:</p><ol>
<li><a href="/Details/123457/uu-no-9-tahun-2015">UU No. 9 Tahun 2015</a> tentang Perubahan atas UU No. 23 Tahun 2014 dan UU No. 2 Tahun 2014.</li>
<li><a href="/Details/123458/uu-no-1-tahun-2026">UU No. 1 Tahun 2026</a> tentang Penyesuaian terhadap UU No. 6 Tahun 2023.</li>
</ol><p>Mencabut:</p><ol><li><a href="/Details/123459/uu-no-32-tahun-2004">UU No. 32 Tahun 2004</a></li></ol>
<h3>ABSTRAK PERATURAN</h3>
<p>Hubungan pusat dan daerah sesuai Undang-Undang Dasar Negara Republik Indonesia Tahun 1945.</p>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $service = app(OfficialParentEvidenceExpansion::class);
        $result = $service->expandBatch($this->graph());
        Http::assertSentCount(2);

        $this->assertSame('PI_R70R_AUTOMATED_ANCESTRY_V1', $result['schema']);
        $this->assertSame(2, $result['parent_evidence_expansion']['read_this_run']);
        $this->assertSame(2, $result['parent_evidence_expansion']['new_constitutional_edges']);
        $this->assertSame(3, $result['parent_evidence_expansion']['total_version_candidates']);
        $this->assertSame(3, $result['parent_evidence_expansion']['new_version_candidates']);
        $this->assertSame('ABSTAIN', $result['parent_evidence_expansion']['legal_status']);
        $this->assertSame('NOT_EXHAUSTIVELY_VERIFIED', $result['parent_evidence_expansion']['amendment_census']);
        $this->assertSame('NOT_ASSESSED', $result['legal_harmonization']);
        $this->assertTrue($result['parent_evidence_expansion']['attempts']['UU-39-2008']
            ['parser_diagnostics']['status_seen']);
        $this->assertTrue($result['parent_evidence_expansion']['attempts']['UU-39-2008']
            ['parser_diagnostics']['amendment_heading_seen']);
        $this->assertSame('BLOCKED', $result['numeric_comparison']);
        $this->assertSame('DENIED', $result['production_admission']);
        $this->assertSame('PI_R70T_PARENT_OFFICIAL_EVIDENCE_V4',
            $result['parent_evidence_expansion']['schema']);
        $this->assertSame('EXACT_LINKED_STATUS_ENTRIES_ONLY',
            $result['parent_evidence_expansion']['candidate_extraction']);
        $this->assertEqualsCanonicalizing([
            'UU-23-2014::UU-9-2015',
            'UU-23-2014::UU-1-2026',
            'UU-39-2008::UU-61-2024',
        ], array_column($result['version_candidates'], 'id'));
        foreach ($result['version_candidates'] as $candidate) {
            $this->assertSame('OFFICIAL_STATUS_LISTED_AS_AMENDING_CANDIDATE', $candidate['relation']);
            $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $candidate['source_html_sha256']);
            $this->assertSame('NOT_ASSESSED', $candidate['legal_effect']);
            $this->assertStringStartsWith('https://peraturan.bpk.go.id/Details/',
                $candidate['amending_metadata_url']);
            $this->assertContains($candidate['link_identity'],
                ['EXACT_SLUG_MATCH', 'LINK_LABEL_ONLY_DETAILS_ID']);
        }

        $second = $service->expandBatch($result);
        Http::assertSentCount(2);
        $this->assertSame(0, $second['parent_evidence_expansion']['attempted_this_run']);
        $this->assertSame(3, $second['parent_evidence_expansion']['total_version_candidates']);
        $this->assertCount(2, $second['edges']);

        // Already-cached V3 candidates might be spurious; discard/re-read them.
        $legacy = $second;
        $legacy['parent_evidence_expansion']['schema'] = 'PI_R70T_PARENT_OFFICIAL_EVIDENCE_V3';
        $legacy['version_candidates'][] = [
            'id' => 'UU-23-2014::UU-2-2014', 'instrument_id' => 'UU-23-2014',
            'amending_candidate_id' => 'UU-2-2014', 'legal_effect' => 'NOT_ASSESSED',
        ];
        $reconciled = $service->expandBatch($legacy);
        Http::assertSentCount(4);
        $this->assertSame(3, $reconciled['parent_evidence_expansion']['total_version_candidates']);
        $this->assertNotContains('UU-23-2014::UU-2-2014',
            array_column($reconciled['version_candidates'], 'id'));
    }

    public function test_provider_failure_or_forged_host_never_yields_parent_evidence(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response('Blocked', 429, ['Content-Type' => 'text/html'])]);
        $graph = $this->graph();
        $graph['nodes'][0]['metadata_url'] = 'https://peraturan.bpk.go.id.evil.example/Details/39719/uu-no-39-tahun-2008';

        $result = app(OfficialParentEvidenceExpansion::class)->expandBatch($graph);
        Http::assertSentCount(1);
        $this->assertSame(0, $result['parent_evidence_expansion']['read_this_run']);
        $this->assertSame(0, $result['parent_evidence_expansion']['new_constitutional_edges']);
        $this->assertSame([], $result['version_candidates']);
        $this->assertSame([], $result['edges']);
        $this->assertSame('OFFICIAL_METADATA_UNAVAILABLE_RETRY',
            $result['parent_evidence_expansion']['attempts']['UU-39-2008']['result']);
    }

    public function test_batch_limits_fail_closed_before_provider_calls(): void
    {
        Http::preventStrayRequests();
        $this->expectException(\RuntimeException::class);
        app(OfficialParentEvidenceExpansion::class)->expandBatch($this->graph(), 20);
        Http::assertNothingSent();
    }
}
