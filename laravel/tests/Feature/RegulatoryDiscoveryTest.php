<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RegulatoryDiscoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-regulatory-r70o/discover';

    public function test_search_requires_auth_and_correct_tenant(): void
    {
        Http::preventStrayRequests();
        $this->getJson(self::URL.'?q=Permendagri')->assertUnauthorized();
        // Browser navigation omits Accept: application/json but is still an API request.
        $this->get(self::URL.'?q=Permendagri')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER-LOCAL-QA']))
            ->getJson(self::URL.'?q=Permendagri')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_real_search_adapter_returns_only_matching_official_candidates(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response(<<<'HTML'
<html><body><h2>PENCARIAN PERATURAN</h2>
<article><a href="/Details/311927/permendagri-no-86-tahun-2017">Permendagri No. 86 Tahun 2017</a></article>
<article><a href="https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025">Permendagri No. 10 Tahun 2025</a></article>
<article><a href="/Details/210451/perbup-kab-purworejo-no-86-tahun-2017">Peraturan Bupati Purworejo Nomor 86 Tahun 2017</a></article>
<a href="https://evil.example/Details/12345/fake">Permendagri No. 86 Tahun 2017</a>
<a href="https://peraturan.bpk.go.id.evil.example/Details/456/hijack">Permendagri No. 86 Tahun 2017</a>
<a href="/Details/100/irrelevant">Peraturan Daerah tentang Lainnya</a>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL.'?q=Permendagri%2086%202017&source=jdih_bpk')
            ->assertOk()
            ->assertJsonPath('schema', 'PI_R70O_OFFICIAL_DISCOVERY_V1')
            ->assertJsonPath('result_status', 'DISCOVERY_ONLY')
            ->assertJsonPath('search_mode', 'STRUCTURED_IDENTITY')
            ->assertJsonPath('amendment_census', 'NOT_EXHAUSTIVELY_VERIFIED')
            ->assertJsonPath('normative_alignment', 'ABSTAIN')
            ->assertJsonPath('items.0.official_metadata_url', 'https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017')
            ->assertJsonPath('items.0.source_pdf_sha256', null)
            ->assertJsonCount(1, 'items')
            ->assertHeader('Cache-Control', 'private, no-store');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://peraturan.bpk.go.id/Search?')
            && str_contains($request->url(), 'keywords=')
            && str_contains($request->url(), 'nomor=86')
            && str_contains($request->url(), 'tahun=2017')
            && str_contains($request->url(), 'jenis=40'));
    }

    public function test_non_identity_query_keeps_keyword_search_without_structured_filters(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response('<h2>PENCARIAN PERATURAN</h2><a href="/Details/311927/permendagri-no-86-tahun-2017">Pedoman Perencanaan Pembangunan Daerah</a>', 200, ['Content-Type' => 'text/html'])]);
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL.'?q=Perencanaan%20Pembangunan&source=jdih_bpk')
            ->assertOk()
            ->assertJsonPath('search_mode', 'KEYWORD_BROAD')
            ->assertJsonCount(1, 'items');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'keywords=Perencanaan')
            && !str_contains($request->url(), 'jenis=40'));
    }

    public function test_broad_query_requires_topic_identity_and_acronym_expansion(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response(<<<'HTML'
<html><body><h2>PENCARIAN PERATURAN</h2>
<a href="/Details/5100/pedoman-apbdes-2026">Pedoman Penyusunan Anggaran Pendapatan dan Belanja Desa Tahun Anggaran 2026</a>
<a href="/Details/321470/permendagri-no-10-tahun-2025">Pedoman Penyusunan Rencana Kerja Pemerintah Daerah Tahun 2026</a>
<a href="/Details/4100/pedoman-rkpd-2025">Pedoman Penyusunan Rencana Kerja Pemerintah Daerah Tahun 2025</a>
</body></html>
HTML, 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $this->getJson(self::URL.'?q=Pedoman%20Penyusunan%20RKPD%202026&source=jdih_bpk')
            ->assertOk()
            ->assertJsonPath('search_mode', 'KEYWORD_BROAD')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.official_metadata_url', 'https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025');

        // The APBD acronym must not match an APBDes title by substring.
        $this->getJson(self::URL.'?q=APBD%202026&source=jdih_bpk')
            ->assertOk()
            ->assertJsonPath('items', []);
        Http::assertSentCount(2);
    }

    public function test_rejected_query_and_provider_failure_never_invent_results(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $this->getJson(self::URL.'?q=ab&source=jdih_bpk')->assertUnprocessable();
        $this->getJson(self::URL.'?q=RKPD&source=https%3A%2F%2Fevil.example')->assertUnprocessable();
        Http::assertNothingSent();

        Http::fake(['peraturan.bpk.go.id/*' => Http::response('<h2>PERATURAN TERBARU</h2>', 200, ['Content-Type' => 'text/html'])]);
        $this->getJson(self::URL.'?q=Permendagri')
            ->assertStatus(503)
            ->assertJsonPath('code', 'OFFICIAL_SOURCE_UNAVAILABLE');

        Http::fake(['peraturan.bpk.go.id/*' => Http::response('Blocked', 429, ['Content-Type' => 'text/html'])]);
        $this->getJson(self::URL.'?q=Permendagri')
            ->assertStatus(503)
            ->assertJsonPath('code', 'OFFICIAL_SOURCE_UNAVAILABLE')
            ->assertJsonPath('items', [])
            ->assertJsonPath('status', 'ABSTAIN');
    }
}
