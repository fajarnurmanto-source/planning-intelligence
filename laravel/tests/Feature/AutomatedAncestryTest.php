<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AutomatedAncestryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-regulatory-r70r/auto-ancestry';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pi.regulatory_auto_ancestry.enabled', true);
        config()->set('pi.regulatory_auto_ancestry.cache_store', 'array');
    }

    public function test_requires_authenticated_tenant_without_contacting_provider(): void
    {
        Http::preventStrayRequests();
        $this->getJson(self::URL)->assertUnauthorized();
        $this->get(self::URL)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER-LOCAL-QA']))
            ->getJson(self::URL)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_official_abstracts_are_read_autonomously_and_parent_citations_remain_unverified(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'peraturan.bpk.go.id/Details/321470/*' => Http::response(<<<'HTML'
<html><body>
<h1>PERATURAN MENTERI DALAM NEGERI NOMOR 10 TAHUN 2025</h1>
<h2>ABSTRAK PERATURAN</h2>
<ul><li>Dasar Hukum Permendagri ini adalah: Undang-Undang Dasar Negara Republik Indonesia Tahun 1945; UU Nomor 39 Tahun 2008; UU Nomor 23 Tahun 2014; UU Nomor 59 Tahun 2024; Perpres Nomor 149 Tahun 2024; Perpres Nomor 12 Tahun 2025; Permendagri Nomor 86 Tahun 2017; Permendagri Nomor 90 Tahun 2019; Permendagri Nomor 9 Tahun 2025.</li></ul>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'peraturan.bpk.go.id/Details/311927/*' => Http::response(<<<'HTML'
<html><body>
<h1>Peraturan Menteri Dalam Negeri Nomor 86 Tahun 2017</h1>
<h2>ABSTRAK PERATURAN</h2>
<ul><li>Dasar Hukum Permendagri ini adalah UU Nomor 39 Tahun 2008 dan UU Nomor 23 Tahun 2014.</li></ul>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL)
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertJsonPath('schema', 'PI_R70R_AUTOMATED_ANCESTRY_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('resolved_seed_count', 2)
            ->assertJsonPath('result_status', 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY')
            ->assertJsonPath('exhaustiveness', 'NOT_EXHAUSTIVELY_VERIFIED')
            ->assertJsonPath('legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('numeric_comparison', 'BLOCKED')
            ->assertJsonPath('production_admission', 'DENIED')
            ->assertJsonPath('seed_documents.0.id', 'PERMENDAGRI-10-2025')
            ->assertJsonPath('seed_documents.1.id', 'PERMENDAGRI-86-2017');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://peraturan.bpk.go.id/Details/'));

        $payload = $this->getJson(self::URL)->assertOk()->json();
        Http::assertSentCount(2); // Second request is served from the no-migration array cache.
        $nodes = collect($payload['nodes'])->keyBy('id');
        $this->assertSame('PINNED_METADATA_FETCHED', $nodes['PERMENDAGRI-86-2017']['official_resolution']);
        $this->assertSame('CITED_IDENTITY_NOT_YET_RESOLVED', $nodes['UU-23-2014']['official_resolution']);
        $this->assertNull($nodes['UU-23-2014']['metadata_url']);
        $this->assertStringStartsWith('https://peraturan.bpk.go.id/Search?', $nodes['UU-23-2014']['search_url']);
        $this->assertContains('UUD-1945', $nodes->keys()->all());
        $this->assertContains('PERMENDAGRI-10-2025', array_column($payload['seed_documents'], 'id'));
        $this->assertTrue((bool) collect($payload['edges'])->first(fn (array $edge) =>
            $edge['from'] === 'PERMENDAGRI-10-2025' && $edge['to'] === 'PERMENDAGRI-86-2017'
            && $edge['relation_status'] === 'CANDIDATE_NOT_LEGAL_FINDING'
        ));
        $this->assertTrue((bool) collect($payload['edges'])->first(fn (array $edge) =>
            $edge['from'] === 'PERMENDAGRI-86-2017' && $edge['to'] === 'UU-23-2014'
        ));
        foreach ($payload['seed_documents'] as $seed) {
            $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $seed['source_html_sha256']);
        }
    }

    public function test_missing_or_wrong_provider_never_claims_complete_ancestry(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response('Blocked', 429, ['Content-Type' => 'text/html'])]);

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL)
            ->assertStatus(503)
            ->assertJsonPath('code', 'AUTOMATED_OFFICIAL_ANCESTRY_UNAVAILABLE')
            ->assertJsonPath('status', 'ABSTAIN')
            ->assertJsonPath('result_status', 'SOURCE_UNAVAILABLE_NO_INVENTED_ANCESTRY');

        Http::fake(['peraturan.bpk.go.id/*' => Http::response(
            '<html><body>ABSTRAK PERATURAN Dasar Hukum Permendagri ini adalah UU Nomor 1 Tahun 2024</body></html>',
            200, ['Content-Type' => 'text/html']
        )]);
        $this->getJson(self::URL)->assertStatus(503);
    }
}
