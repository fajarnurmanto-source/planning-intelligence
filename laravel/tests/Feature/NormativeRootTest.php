<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class NormativeRootTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_official_2026_parent_rule_trace_is_tenant_scoped_and_abstains(): void
    {
        $url = '/api/qa-regulatory-r70l/normative-root';
        $this->getJson($url)->assertUnauthorized();

        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER-LOCAL-QA']))
            ->getJson($url)->assertForbidden();

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson($url)->assertOk()
            ->assertJsonPath('schema', 'PI_R70L_NORMATIVE_ROOT_READ_MODEL_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('review_year', 2026)
            ->assertJsonPath('as_of_date', '2026-11-08')
            ->assertJsonCount(2, 'instruments')
            ->assertJsonCount(6, 'provisions')
            ->assertJsonPath('instruments.1.effective_date', '2025-06-23')
            ->assertJsonPath('instruments.1.pdf_sha256', null)
            ->assertJsonPath('provisions.0.locator', 'Pasal 76; halaman cetak -40-')
            ->assertJsonPath('provisions.3.locator', 'Pasal 3 ayat (5); halaman cetak -5-')
            ->assertJsonPath('delegation.type', 'EXPLICIT_NORMATIVE_DELEGATION_REFERENCE')
            ->assertJsonPath('case.planning_document_kind', 'RKPD')
            ->assertJsonPath('case.regional_exception_applicability', 'PROVINCE_DIY_ONLY_NOT_CITY_YOGYAKARTA')
            ->assertJsonPath('case.actual_compliance', 'NOT_ASSESSED')
            ->assertJsonPath('case.admitted_alignment', null)
            ->assertJsonPath('gates.normative_root_trace', 'PARTIAL_PRIMARY_PROVISIONS_FOUND')
            ->assertJsonPath('gates.regulatory_version', 'AMENDMENT_CHAIN_INCOMPLETE')
            ->assertJsonPath('gates.result', 'ABSTAIN')
            ->assertJsonPath('gates.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('gates.production_admission', 'DENIED')
            ->assertHeader('Cache-Control', 'private, no-store');
    }

    public function test_unproven_amendment_completion_province_exception_or_forged_source_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $registry = json_decode(
            file_get_contents(resource_path('pi/normative-root-r70l.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $forged = $registry;
        $forged['gates']['regulatory_version'] = 'AMENDMENT_CHAIN_COMPLETE';
        $forged['gates']['result'] = 'ALIGNED';
        $this->useRegistry($forged);
        $this->assertUnavailable();

        $forged = $registry;
        $forged['case']['regional_exception_applicability'] = 'CITY_YOGYAKARTA';
        $this->useRegistry($forged);
        $this->assertUnavailable();

        $forged = $registry;
        $forged['instruments'][1]['official_pdf_url'] = 'https://evil.invalid/fake.pdf';
        $this->useRegistry($forged);
        $this->assertUnavailable();

        $forged = $registry;
        $forged['provisions'][3]['summary'] = 'Semua program dalam RKPD pasti selaras dan legal.';
        $this->useRegistry($forged);
        $this->assertUnavailable();
    }

    private function assertUnavailable(): void
    {
        $this->getJson('/api/qa-regulatory-r70l/normative-root')
            ->assertStatus(503)
            ->assertJsonPath('code', 'NORMATIVE_ROOT_SOURCE_UNAVAILABLE');
    }

    private function useRegistry(array $registry): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pi-r70l-');
        if ($file === false) {
            throw new \RuntimeException('Could not allocate R70L fixture.');
        }
        file_put_contents($file, json_encode($registry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->temporaryFiles[] = $file;
        config()->set('pi.normative_root.registry_path', $file);
    }
}
