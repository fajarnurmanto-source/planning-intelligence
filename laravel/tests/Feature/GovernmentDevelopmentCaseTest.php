<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class GovernmentDevelopmentCaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-superior-r85/development-case';

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

    public function test_case_is_authenticated_tenant_scoped_filterable_and_fail_closed(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson(self::URL)->assertForbidden();

        $response = $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson(self::URL.'?jurisdiction=ID-DIY-YOG&year=2026&topic=PERSAMPAHAN&case=YOG-2026-PERSAMPAHAN');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema', 'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_READ_MODEL_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('result_count', 1)
            ->assertJsonPath('case.id', 'YOG-2026-PERSAMPAHAN')
            ->assertJsonPath('case.questions.0.id', 'why')
            ->assertJsonPath('case.questions.4.id', 'two_way_trace')
            ->assertJsonPath('interpretation.legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.review_decision', 'NO_ACTIVE_DECISION')
            ->assertJsonPath('interpretation.production_admission', 'DENIED')
            ->assertJsonCount(5, 'case.questions')
            ->assertJsonCount(4, 'case.graphs');

        $payload = $response->json();
        $this->assertSame([
            'Authority', 'BudgetArtifact', 'EvidenceLocator', 'GovernmentAffair', 'Indicator',
            'PlanningArtifactVersion', 'PlanningProgram', 'ProcessEvent', 'ProcessStage',
            'Provision', 'RegulationVersion', 'ReviewDecision',
        ], $this->nodeTypes($payload['case']['nodes']));

        foreach ($payload['case']['edges'] as $edge) {
            $this->assertSame([
                'as_of_date', 'jurisdiction', 'locator', 'source_id', 'source_sha256',
                'source_url', 'uncertainty', 'verification_status', 'version',
            ], $this->sortedKeys($edge['provenance']));
        }

        $this->getJson(self::URL.'?topic=KESEHATAN')
            ->assertOk()
            ->assertJsonPath('result_count', 0)
            ->assertJsonPath('case', null);
        $this->getJson(self::URL.'?year=not-a-year')->assertUnprocessable();
    }

    public function test_missing_provenance_or_unsafe_interpretation_rejects_the_registry(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $registry = json_decode(
            file_get_contents(resource_path('pi/development-case-r85.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        unset($registry['case']['edges'][0]['provenance']['locator']);
        $this->useRegistry($registry);
        $this->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('code', 'DEVELOPMENT_CASE_SOURCE_UNAVAILABLE');

        $registry = json_decode(
            file_get_contents(resource_path('pi/development-case-r85.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $registry['interpretation']['legal_harmonization'] = 'COMPLIANT';
        $this->useRegistry($registry);
        $this->getJson(self::URL)->assertStatus(503)
            ->assertJsonPath('status', 'ABSTAIN');
    }

    /** @param list<array<string, mixed>> $nodes
     * @return list<string>
     */
    private function nodeTypes(array $nodes): array
    {
        $types = array_values(array_unique(array_column($nodes, 'type')));
        sort($types);

        return $types;
    }

    /** @param array<string, mixed> $value
     * @return list<string>
     */
    private function sortedKeys(array $value): array
    {
        $keys = array_keys($value);
        sort($keys);

        return $keys;
    }

    /** @param array<string, mixed> $registry */
    private function useRegistry(array $registry): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pi-r85-');
        if ($file === false) {
            throw new \RuntimeException('Unable to write temporary R85 fixture.');
        }
        file_put_contents($file, json_encode($registry, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $file;
        config()->set('pi.development_case.registry_path', $file);
    }
}
