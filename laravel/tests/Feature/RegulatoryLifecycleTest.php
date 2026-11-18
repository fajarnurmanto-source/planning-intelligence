<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RegulatoryLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-superior-r86/regulatory-lifecycle';

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

    public function test_r86_is_authenticated_tenant_scoped_and_reuses_the_r85_case_contract(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson(self::URL)->assertForbidden();

        $response = $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL.'?case=YOG-2026-PERSAMPAHAN&jurisdiction=ID-DIY-YOG&year=2026&as_of=2026-11-18');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema', 'PI_R86_REGULATORY_DEVELOPMENT_LIFECYCLE_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('catalogue.entry_count', 38)
            ->assertJsonPath('catalogue.admission', 'CANDIDATE_CATALOGUE_NOT_LEGAL_VALIDATION')
            ->assertJsonPath('result_count', 1)
            ->assertJsonPath('case.id', 'YOG-2026-PERSAMPAHAN')
            ->assertJsonPath('case.r85_contract.schema', 'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_READ_MODEL_V1')
            ->assertJsonPath('case.lifecycle.model', 'DEPENDENCY_GRAPH_NOT_SINGLE_LINEAR_TIMELINE')
            ->assertJsonPath('case.lifecycle.stage_count', 10)
            ->assertJsonPath('case.lifecycle.returned_stage_count', 10)
            ->assertJsonPath('case.executive_review_brief.human_decision', 'NO_ACTIVE_DECISION')
            ->assertJsonPath('interpretation.legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.review_decision', 'NO_ACTIVE_DECISION')
            ->assertJsonPath('interpretation.production_admission', 'DENIED')
            ->assertJsonCount(38, 'catalogue.entries')
            ->assertJsonCount(5, 'case.r85_contract.question_ids')
            ->assertJsonCount(4, 'case.r85_contract.graph_ids')
            ->assertJsonCount(10, 'case.lifecycle.stages')
            ->assertJsonCount(5, 'case.specialized_views');

        $payload = $response->json();
        $this->assertSame('EVIDENCE_NOT_AVAILABLE', $this->stage($payload, 'internal_oversight')['evidence_status']);
        $this->assertSame('EVIDENCE_NOT_AVAILABLE', $this->stage($payload, 'external_audit')['evidence_status']);
        $this->assertNotSame(
            $this->stage($payload, 'internal_oversight')['actor'],
            $this->stage($payload, 'external_audit')['actor']
        );
        $this->assertSame('VERIFIED_SOURCE_BYTES', $payload['case']['evidence'][2]['verification_status']);
        $this->assertSame(
            '8D585DA7E9664B64CED5358ECA8D1FBE935F4A6D6680CF5F7C83AA11366C3F8D',
            $payload['case']['evidence'][2]['source_sha256']
        );
    }

    public function test_temporal_rules_keep_2027_guidance_out_of_the_2026_case_and_delay_spkn_transition(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));

        $payload = $this->getJson(self::URL.'?as_of=2026-11-18')->assertOk()->json();
        $this->assertSame('CURRENTLY_APPLICABLE', $this->temporal($payload, 'REG-PMD10-2025')['status']);
        $this->assertSame('OUT_OF_SCOPE_CASE_YEAR', $this->temporal($payload, 'REG-PMD14-2026')['status']);
        $this->assertSame('OUT_OF_SCOPE_CASE_YEAR', $this->temporal($payload, 'REG-PMD17-2026')['status']);
        $this->assertSame('CURRENTLY_APPLICABLE', $this->temporal($payload, 'REG-PBPK1-2017')['status']);
        $this->assertSame('NOT_YET_EFFECTIVE', $this->temporal($payload, 'REG-PBPK1-2026')['status']);

        $dayBefore = $this->getJson(self::URL.'?as_of=2028-04-09')->assertOk()->json();
        $this->assertSame('CURRENTLY_APPLICABLE', $this->temporal($dayBefore, 'REG-PBPK1-2017')['status']);
        $this->assertSame('NOT_YET_EFFECTIVE', $this->temporal($dayBefore, 'REG-PBPK1-2026')['status']);

        $transition = $this->getJson(self::URL.'?as_of=2028-04-10')->assertOk()->json();
        $this->assertSame('REPEALED_OR_SUPERSEDED', $this->temporal($transition, 'REG-PBPK1-2017')['status']);
        $this->assertSame('CURRENTLY_APPLICABLE', $this->temporal($transition, 'REG-PBPK1-2026')['status']);
    }

    public function test_stage_filter_mismatch_and_invalid_filters_fail_closed(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));

        $this->getJson(self::URL.'?stage=external_audit')
            ->assertOk()
            ->assertJsonPath('case.lifecycle.returned_stage_count', 1)
            ->assertJsonPath('case.lifecycle.stages.0.id', 'external_audit');

        $this->getJson(self::URL.'?year=2027')
            ->assertOk()
            ->assertJsonPath('result_count', 0)
            ->assertJsonPath('case', null);

        $this->getJson(self::URL.'?stage=unknown')->assertUnprocessable();
        $this->getJson(self::URL.'?as_of=18-11-2026')->assertUnprocessable();
        $this->getJson(self::URL.'?jurisdiction=https://attacker.example')->assertUnprocessable();
    }

    public function test_unsafe_source_missing_temporal_provision_and_pdf_hash_drift_reject_the_registry(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $registry = $this->registry();

        $registry['regulation_versions'][0]['source_url'] = 'https://attacker.example/claim.pdf';
        $this->useRegistry($registry);
        $this->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('code', 'REGULATORY_BACKBONE_SOURCE_UNAVAILABLE');

        $registry = $this->registry();
        $registry['regulation_versions'][17]['source_sha256'] = str_repeat('A', 64);
        $this->useRegistry($registry);
        $this->getJson(self::URL)->assertStatus(503);

        $registry = $this->registry();
        $registry['provisions'] = array_values(array_filter(
            $registry['provisions'],
            static fn (array $provision): bool => $provision['id'] !== 'PROV-PBPK1-2026-P9'
        ));
        $this->useRegistry($registry);
        $this->getJson(self::URL)->assertStatus(503);
    }

    public function test_catalogue_hash_drift_and_duplicate_regulation_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $source = file_get_contents(resource_path('pi/regulatory-backbone-catalogue-r86.csv'));
        $this->assertIsString($source);

        $drifted = $source."\n";
        $this->useCatalogue($drifted, null);
        $this->getJson(self::URL)->assertStatus(503);

        $lines = preg_split('/\R/', trim($source));
        $this->assertIsArray($lines);
        $duplicate = implode("\n", [...$lines, $lines[1]])."\n";
        $this->useCatalogue($duplicate, strtoupper(hash('sha256', $duplicate)));
        $this->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN');
    }

    /** @return array<string, mixed> */
    private function registry(): array
    {
        return json_decode(
            file_get_contents(resource_path('pi/regulatory-backbone-lifecycle-r86.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function stage(array $payload, string $id): array
    {
        foreach ($payload['case']['lifecycle']['stages'] as $stage) {
            if ($stage['id'] === $id) {
                return $stage;
            }
        }
        $this->fail('Lifecycle stage missing: '.$id);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function temporal(array $payload, string $id): array
    {
        foreach ($payload['case']['temporal_rules'] as $item) {
            if ($item['regulation_version_id'] === $id) {
                return $item;
            }
        }
        $this->fail('Temporal regulation missing: '.$id);
    }

    /** @param array<string, mixed> $registry */
    private function useRegistry(array $registry): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pi-r86-registry-');
        if ($file === false) {
            throw new \RuntimeException('Unable to write temporary R86 registry.');
        }
        file_put_contents($file, json_encode($registry, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $file;
        config()->set('pi.regulatory_backbone.registry_path', $file);
    }

    private function useCatalogue(string $contents, ?string $expectedHash): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pi-r86-catalogue-');
        if ($file === false) {
            throw new \RuntimeException('Unable to write temporary R86 catalogue.');
        }
        file_put_contents($file, $contents);
        $this->temporaryFiles[] = $file;
        config()->set('pi.regulatory_backbone.catalogue_path', $file);
        if ($expectedHash !== null) {
            config()->set('pi.regulatory_backbone.catalogue_sha256', $expectedHash);
        }
    }
}
