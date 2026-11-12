<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Regulatory\RegulatoryDelegationCoverage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RegulatoryDelegationCoverageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_r80_identifies_document_candidates_without_claiming_legal_evidence(): void
    {
        Http::preventStrayRequests();
        $result = app(RegulatoryDelegationCoverage::class)->project();
        $this->assertSame('PI_R80_DELEGATION_COVERAGE_QA_V1', $result['schema']);
        $this->assertSame(7, $result['candidate_article_count']);
        $this->assertSame(1, $result['curated_locator_article_count']);
        $this->assertSame(6, $result['missing_curated_article_count']);
        $this->assertSame(1, $result['source_count']);
        $this->assertSame('DENIED', $result['production_admission']);
        $this->assertSame('NOT_ASSESSED', $result['legal_harmonization']);
        $this->assertSame('PARTIAL_NONEXHAUSTIVE_CANDIDATES', $result['source_coverage']);
        $this->assertNull($result['sources'][0]['source_pdf_sha256']);
        $this->assertSame([2], $result['sources'][0]['candidates'][0]['paragraphs']);
        $this->assertSame([3, 4], $result['sources'][0]['candidates'][5]['paragraphs']);
        $this->assertSame('UU-18-2008:PASAL-16', $result['sources'][0]['candidates'][1]['target_provision_id']);
        $this->assertSame('CURATED_LOCATOR_NOT_HASH_PINNED', $result['sources'][0]['candidates'][1]['coverage_state']);
        $this->assertSame('CANDIDATE_NOT_YET_IN_R74', $result['sources'][0]['candidates'][2]['coverage_state']);
        foreach ($result['sources'][0]['candidates'] as $candidate) {
            $this->assertSame('NOT_ASSESSED', $candidate['legal_effect']);
            $this->assertSame('REVIEW_REQUIRED', $candidate['review_status']);
        }
        Http::assertNothingSent();
    }

    public function test_r80_rejects_unknown_source_duplicate_article_and_untrusted_url(): void
    {
        $service = app(RegulatoryDelegationCoverage::class);
        $baseline = json_decode((string) file_get_contents(resource_path(
            'pi/regulatory-delegation-coverage-r80.json'
        )), true, 64, JSON_THROW_ON_ERROR);
        foreach (['untrusted_url', 'duplicate', 'unknown'] as $scenario) {
            $copy = $baseline;
            if ($scenario === 'untrusted_url') {
                $copy['sources'][0]['official_pdf_url'] = 'https://evil.example/x.pdf';
            } elseif ($scenario === 'duplicate') {
                $copy['sources'][0]['provisions'][] = $copy['sources'][0]['provisions'][0];
            } else {
                $copy['sources'][0]['target_instrument_id'] = 'UU-99-9999';
            }
            $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pi-r80-'.bin2hex(random_bytes(8)).'.json';
            $this->tmp[] = $path;
            file_put_contents($path, json_encode($copy, JSON_THROW_ON_ERROR));
            try {
                $service->project($path);
                $this->fail($scenario.' must be rejected');
            } catch (\RuntimeException $exception) {
                $this->assertStringStartsWith('R80_', $exception->getMessage());
            }
        }
    }

    public function test_r80_api_is_tenant_gated_read_only_without_cloud_requests(): void
    {
        Http::preventStrayRequests();
        $url = '/api/qa-regulatory-r80/delegation-coverage';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson($url)->assertOk()
            ->assertJsonPath('candidate_article_count', 7)
            ->assertJsonPath('curated_locator_article_count', 1)
            ->assertJsonPath('missing_curated_article_count', 6)
            ->assertJsonPath('production_admission', 'DENIED');
        Http::assertNothingSent();
    }
}
