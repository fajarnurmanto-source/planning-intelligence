<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Regulatory\RegulatoryGenealogyGraph;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RegulatoryGenealogyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ENDPOINT = '/api/qa-regulatory-r74/genealogy';

    public function test_r74_endpoints_require_authenticated_institution(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/qa-regulatory-r74/taxonomy')->assertUnauthorized();
        $this->getJson(self::ENDPOINT.'?root=UU-18-2008')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER-LOCAL-QA']))
            ->getJson('/api/qa-regulatory-r74/taxonomy')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_statutory_hierarchy_has_exact_article_7_ranks_and_no_article_8_rank(): void
    {
        $data = app(RegulatoryTaxonomyRegistry::class)->load();
        $types = array_column($data['types'], null, 'code');
        $this->assertSame(1, $types['UUD']['rank']);
        $this->assertSame(2, $types['TAP_MPR']['rank']);
        $this->assertSame(3, $types['UU']['rank']);
        $this->assertSame(3, $types['PERPU']['rank']);
        $this->assertSame(4, $types['PP']['rank']);
        $this->assertSame(5, $types['PERPRES']['rank']);
        $this->assertSame(6, $types['PERDA_PROV']['rank']);
        $this->assertSame(7, $types['PERDA_KABKOTA']['rank']);
        foreach ($data['types'] as $type) {
            if ($type['group'] !== 'ARTICLE_7') {
                $this->assertNull($type['rank'], $type['code']);
            }
        }
        $topics = array_column($data['topics'], null, 'id');
        $this->assertSame(
            ['TOPIC:LINGKUNGAN', 'TOPIC:PELAYANAN_PUBLIK'],
            $topics['TOPIC:PERSAMPAHAN']['broader']
        );
    }

    public function test_legal_ancestry_and_descendants_are_reversible_at_provision_level(): void
    {
        Http::preventStrayRequests();
        $service = app(RegulatoryGenealogyGraph::class);
        $graph = $service->build(app(RegulatoryTaxonomyRegistry::class)->load());

        $ancestors = $service->traverse($graph, 'PP-81-2012', 'ancestors', 'legal', 3);
        $ids = array_column($ancestors['nodes'], 'id');
        $this->assertContains('UU-18-2008:PASAL-16', $ids);
        $this->assertContains('UU-18-2008', $ids);
        $this->assertContains('PP-81-2012', $ids);
        $this->assertNotContains('TOPIC:PERSAMPAHAN', $ids);
        $this->assertContains('IMPLEMENTS_PROVISION_OF', array_column($ancestors['relations'], 'type'));

        $descendants = $service->traverse($graph, 'UU-18-2008', 'descendants', 'legal', 3);
        $this->assertContains('PP-81-2012', array_column($descendants['nodes'], 'id'));
        $this->assertSame('NOT_ASSESSED', $descendants['legal_harmonization']);
        $this->assertSame('DENIED', $descendants['production_admission']);
        Http::assertNothingSent();
    }

    public function test_thematic_genealogy_is_multi_parent_and_not_legal_ancestry(): void
    {
        $service = app(RegulatoryGenealogyGraph::class);
        $graph = $service->build(app(RegulatoryTaxonomyRegistry::class)->load());
        $trace = $service->traverse($graph, 'UU-18-2008', 'both', 'thematic', 4);
        $ids = array_column($trace['nodes'], 'id');
        $this->assertContains('TOPIC:PERSAMPAHAN', $ids);
        $this->assertContains('TOPIC:LINGKUNGAN', $ids);
        $this->assertContains('TOPIC:PELAYANAN_PUBLIK', $ids);
        $this->assertContains('PP-81-2012', $ids);
        $this->assertNotContains('IMPLEMENTS_PROVISION_OF', array_column($trace['relations'], 'type'));
    }

    public function test_amendment_genealogy_preserves_partial_provision_target(): void
    {
        $service = app(RegulatoryGenealogyGraph::class);
        $graph = $service->build(app(RegulatoryTaxonomyRegistry::class)->load());
        $trace = $service->traverse($graph, 'UU-12-2011', 'descendants', 'version', 3);
        $ids = array_column($trace['nodes'], 'id');
        $this->assertContains('UU-15-2019', $ids);
        $this->assertContains('UU-13-2022', $ids);
        $this->assertContains('UU-1-2026', $ids);
        $this->assertContains('UU-12-2011:PASAL-15', $ids);
        $this->assertSame('NOT_ASSESSED', $trace['temporal_applicability']);
        $this->assertContains('AMENDS_PROVISION_OF', array_column($trace['relations'], 'type'));
    }

    public function test_r70r_citations_remain_candidates_not_automatically_delegation(): void
    {
        $seed = app(RegulatoryTaxonomyRegistry::class)->load();
        $legacy = [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'tenant_id' => config('pi.tenant_id'),
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'nodes' => [
                ['id' => 'UU-25-2004', 'kind' => 'UU', 'metadata_url' => null],
                ['id' => 'PERMENDAGRI-10-2025', 'kind' => 'PERMENDAGRI', 'metadata_url' => null],
            ],
            'edges' => [[
                'from' => 'PERMENDAGRI-10-2025', 'to' => 'UU-25-2004',
                'evidence_url' => 'https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025',
                'citation_text' => 'UU Nomor 25 Tahun 2004',
                'relation_status' => 'CANDIDATE_NOT_LEGAL_FINDING',
            ]],
        ];
        $service = app(RegulatoryGenealogyGraph::class);
        $graph = $service->build($seed, $legacy);
        $trace = $service->traverse($graph, 'PERMENDAGRI-10-2025', 'ancestors', 'legal', 2);
        $this->assertContains('UU-25-2004', array_column($trace['nodes'], 'id'));
        $this->assertTrue((bool) array_filter($trace['relations'], static fn (array $relation): bool =>
            $relation['type'] === 'CITES_AS_BASIS'
            && $relation['status'] === 'R70R_CANDIDATE_NOT_LEGAL_FINDING'));
        $this->assertSame(1, $trace['legacy_candidate_edge_count']);
    }

    public function test_api_returns_bounded_graph_without_network_or_legal_approval(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $catalogue = $this->getJson('/api/qa-regulatory-r74/taxonomy')->assertOk()
            ->assertJsonPath('schema', 'PI_R74_REGULATORY_TAXONOMY_V1')
            ->assertJsonPath('production_admission', 'DENIED');
        $this->assertStringContainsString('no-store', (string) $catalogue->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $catalogue->headers->get('Cache-Control'));
        $trace = $this->getJson(self::ENDPOINT.'?root=PP-81-2012&direction=ancestors&lens=legal&depth=3')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1')
            ->assertJsonPath('result_status', 'PARTIAL_EVIDENCE_GRAPH_NOT_LEGAL_FINDING')
            ->assertJsonPath('numeric_comparison', 'BLOCKED');
        $this->assertStringContainsString('no-store', (string) $trace->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $trace->headers->get('Cache-Control'));
        $this->getJson(self::ENDPOINT.'?root=NO-99-2099')->assertNotFound()
            ->assertJsonPath('code', 'UNKNOWN_REGULATORY_NODE');
        $this->getJson(self::ENDPOINT.'?root=UU-18-2008&depth=99')->assertUnprocessable();
        $this->getJson(self::ENDPOINT.'?root=https%3A%2F%2Fevil.example')->assertUnprocessable();
        Http::assertNothingSent();
    }
}
