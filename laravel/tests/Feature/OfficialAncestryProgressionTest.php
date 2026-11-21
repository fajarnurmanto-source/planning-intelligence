<?php

namespace Tests\Feature;

use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OfficialAncestryProgressionTest extends TestCase
{
    private function graph(): array
    {
        return [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'tenant_id' => config('pi.tenant_id'),
            'source' => 'JDIH_BPK_OFFICIAL_METADATA',
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'nodes' => [
                ['id' => 'UU-39-2008', 'kind' => 'UU', 'number' => 39, 'year' => 2008,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED',
                    'metadata_url' => null],
                ['id' => 'UUD-1945', 'kind' => 'UUD', 'number' => null, 'year' => 1945,
                    'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED',
                    'metadata_url' => null],
            ],
            'edges' => [],
            'unresolved_parent_count' => 2,
            'exhaustiveness' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    public function test_single_unattended_command_resolves_then_reads_and_checkpoints_both_stages(): void
    {
        config()->set('pi.regulatory_auto_ancestry.enabled', true);
        config()->set('pi.regulatory_auto_ancestry.cache_store', 'array');
        Cache::store('array')->put(OfficialNormativeAncestry::CACHE_KEY, $this->graph(), now()->addHours(1));
        Http::preventStrayRequests();
        Http::fake([
            'peraturan.bpk.go.id/Search*' => Http::response(<<<'HTML'
<html><body><h1>PENCARIAN PERATURAN</h1>
<a href="/Details/39719/uu-no-39-tahun-2008">Undang-undang No. 39 Tahun 2008</a>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'peraturan.bpk.go.id/Details/39719/*' => Http::response(<<<'HTML'
<html><body><h1>Undang-undang (UU) No. 39 Tahun 2008 Kementerian Negara</h1>
<h3>STATUS PERATURAN</h3><p>Diubah dengan:</p>
<ol><li><a href="/Details/55555/uu-no-61-tahun-2024">UU No. 61 Tahun 2024</a> tentang Perubahan atas UU No. 39 Tahun 2008</li></ol>
<h3>ABSTRAK PERATURAN</h3>
<p>Pasal 4, Pasal 17, Pasal 20, dan Pasal 21 Undang-Undang Dasar Negara Republik Indonesia Tahun 1945.</p>
</body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $this->assertSame(0, Artisan::call('pi:regulatory-ancestry:advance'));
        $output = Artisan::output();
        $this->assertStringContainsString('PI_R70U=BOUNDED_ANCESTRY_PROGRESSION_COMPLETED',
            $output);
        $this->assertStringContainsString('LOOKUPS=1 MATCHED=1 PARENT_PAGES_READ=1',
            $output);
        Http::assertSentCount(2);

        $saved = Cache::store('array')->get(OfficialNormativeAncestry::CACHE_KEY);
        $this->assertSame('OFFICIAL_CATALOG_IDENTITY_CANDIDATE',
            $saved['nodes'][0]['official_resolution']);
        $this->assertSame('PI_R70T_PARENT_OFFICIAL_EVIDENCE_V4',
            $saved['parent_evidence_expansion']['schema']);
        $this->assertSame('UU-39-2008::UU-61-2024',
            $saved['version_candidates'][0]['id']);
        $this->assertSame('ABSTAIN',
            $saved['parent_evidence_expansion']['legal_status']);
        $this->assertSame('BLOCKED', $saved['numeric_comparison']);
        $this->assertSame('DENIED', $saved['production_admission']);

        $this->assertSame(0, Artisan::call('pi:regulatory-ancestry:advance'));
        $output = Artisan::output();
        $this->assertStringContainsString('LOOKUPS=0 MATCHED=0 PARENT_PAGES_READ=0',
            $output);
        Http::assertSentCount(2);
    }

    public function test_disabled_automatic_ancestry_never_calls_official_provider(): void
    {
        config()->set('pi.regulatory_auto_ancestry.enabled', false);
        config()->set('pi.regulatory_auto_ancestry.cache_store', 'array');
        Http::preventStrayRequests();

        $this->assertSame(0, Artisan::call('pi:regulatory-ancestry:advance'));
        $this->assertStringContainsString('PI_R70U=DISABLED', Artisan::output());
        Http::assertNothingSent();
    }
}
