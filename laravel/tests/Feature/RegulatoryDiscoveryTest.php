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
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER-LOCAL-QA']))
            ->getJson(self::URL.'?q=Permendagri')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_real_search_adapter_returns_only_matching_official_candidates(): void
    {
        Http::preventStrayRequests();
        Http::fake(['peraturan.bpk.go.id/*' => Http::response(<<<'HTML'
<html><body>
<article><a href="/Details/311927/permendagri-no-86-tahun-2017">Permendagri No. 86 Tahun 2017</a></article>
<article><a href="https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025">Permendagri No. 10 Tahun 2025</a></article>
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
            ->assertJsonPath('amendment_census', 'NOT_EXHAUSTIVELY_VERIFIED')
            ->assertJsonPath('normative_alignment', 'ABSTAIN')
            ->assertJsonPath('items.0.official_metadata_url', 'https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017')
            ->assertJsonPath('items.0.source_pdf_sha256', null)
            ->assertJsonCount(1, 'items')
            ->assertHeader('Cache-Control', 'private, no-store');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://peraturan.bpk.go.id/Search?')
            && str_contains($request->url(), 'keywords=Permendagri'));
    }

    public function test_rejected_query_and_provider_failure_never_invent_results(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $this->getJson(self::URL.'?q=ab&source=jdih_bpk')->assertUnprocessable();
        $this->getJson(self::URL.'?q=RKPD&source=https%3A%2F%2Fevil.example')->assertUnprocessable();
        Http::assertNothingSent();

        Http::fake(['peraturan.bpk.go.id/*' => Http::response('Blocked', 429, ['Content-Type' => 'text/html'])]);
        $this->getJson(self::URL.'?q=Permendagri')
            ->assertStatus(503)
            ->assertJsonPath('code', 'OFFICIAL_SOURCE_UNAVAILABLE')
            ->assertJsonPath('items', [])
            ->assertJsonPath('status', 'ABSTAIN');
    }
}
