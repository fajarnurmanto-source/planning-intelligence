<?php

namespace Tests\Feature;

use App\Services\Pi\Regulatory\OfficialParentResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OfficialParentResolverTest extends TestCase
{
    private function graph(): array
    {
        return [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'nodes' => [
                ['id' => 'PERMENDAGRI-90-2019', 'kind' => 'PERMENDAGRI', 'number' => 90,
                    'year' => 2019, 'metadata_url' => null,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED'],
                ['id' => 'UU-23-2014', 'kind' => 'UU', 'number' => 23,
                    'year' => 2014, 'metadata_url' => null,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED'],
                ['id' => 'PERPRES-149-2024', 'kind' => 'PERPRES', 'number' => 149,
                    'year' => 2024, 'metadata_url' => null,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED'],
                ['id' => 'UUD-1945', 'kind' => 'UUD', 'number' => null,
                    'year' => 1945, 'metadata_url' => null,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED'],
                ['id' => 'UU-39-2008', 'kind' => 'UU', 'number' => 39,
                    'year' => 2008, 'metadata_url' => null,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED'],
            ],
            'unresolved_parent_count' => 5,
            'exhaustiveness' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    public function test_exact_official_metadata_candidates_are_resolved_without_per_law_user_search(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $this->assertStringStartsWith('https://peraturan.bpk.go.id/Search?', $request->url());
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);
            $number = (int) ($params['nomor'] ?? 0);
            $year = (int) ($params['tahun'] ?? 0);
            $slugs = [
                23 => 'uuno-23-tahun-2014',
                39 => 'uu-no-39-tahun-2008',
                149 => 'perpres-no-149-tahun-2024',
                90 => 'permendagri-no-90-tahun-2019',
            ];
            $this->assertArrayHasKey($number, $slugs);
            $this->assertSame($number === 90 ? 2019 : ($number === 149 ? 2024 : ($number === 23 ? 2014 : 2008)), $year);
            if ($number === 90) {
                $this->assertSame('40', $params['jenis'] ?? null);
            }
            $slug = $slugs[$number];

            return Http::response('<html><body><h1>PENCARIAN PERATURAN</h1>'.
                '<a href="https://evil.example/Details/999/'.$slug.'">FAKE</a>'.
                '<a href="/Details/'.($number + 1000).'/'.$slug.'">Sumber resmi '.$slug.'</a>'.
                '</body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
        });

        $resolver = app(OfficialParentResolver::class);
        $first = $resolver->resolveBatch($this->graph());
        $this->assertSame(3, $first['parent_resolution_batch']['attempted_this_run']);
        $this->assertSame(3, $first['parent_resolution_batch']['matched_this_run']);
        $this->assertSame(3, $first['parent_resolution_batch']['catalog_matched_count']);
        $this->assertSame(2, $first['unresolved_parent_count']);
        $this->assertSame('ABSTAIN', $first['parent_resolution_batch']['legal_status']);
        $this->assertSame('NOT_EXHAUSTIVELY_VERIFIED', $first['parent_resolution_batch']['amendment_census']);
        $this->assertSame('DENIED', $first['production_admission']);
        Http::assertSentCount(3);

        $second = $resolver->resolveBatch($first);
        Http::assertSentCount(4);
        $this->assertSame(1, $second['parent_resolution_batch']['attempted_this_run']);
        $this->assertSame(4, $second['parent_resolution_batch']['catalog_matched_count']);
        $this->assertSame(1, $second['unresolved_parent_count']); // UUD remains unverified, not claimed to be complete.
        $this->assertSame('OFFICIAL_CATALOG_IDENTITY_CANDIDATE', $second['nodes'][0]['official_resolution']);
        $this->assertSame('CITED_IDENTITY_NOT_YET_RESOLVED', $second['nodes'][3]['official_resolution']);
        $this->assertSame('PI_R70R_AUTOMATED_ANCESTRY_V1', $second['schema']);
        $this->assertSame('AUTOMATED_ANCESTRY_CANDIDATES_ONLY', $second['result_status']);
    }

    public function test_failure_is_bounded_and_cannot_invent_an_official_parent_link(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response('Blocked', 429, ['Content-Type' => 'text/html'])]);

        $result = app(OfficialParentResolver::class)->resolveBatch($this->graph());
        Http::assertSentCount(3);
        $this->assertSame(0, $result['parent_resolution_batch']['matched_this_run']);
        $this->assertSame(5, $result['unresolved_parent_count']);
        $this->assertSame('OFFICIAL_SEARCH_UNAVAILABLE_RETRY',
            $result['parent_resolution_batch']['attempts']['UU-23-2014']['result']);
        $this->assertNull($result['nodes'][1]['metadata_url']);
        $this->assertSame('CITED_IDENTITY_NOT_YET_RESOLVED', $result['nodes'][1]['official_resolution']);
    }

    public function test_invalid_graph_or_unbounded_request_is_refused_before_any_network_call(): void
    {
        Http::preventStrayRequests();
        $resolver = app(OfficialParentResolver::class);

        try {
            $resolver->resolveBatch($this->graph(), 40);
            $this->fail('Unbounded lookup should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Ancestry graph or lookup batch limit is invalid.', $e->getMessage());
        }
        Http::assertNothingSent();
    }
}
