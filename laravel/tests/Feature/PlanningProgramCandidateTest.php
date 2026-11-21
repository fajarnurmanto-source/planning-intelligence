<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class PlanningProgramCandidateTest extends TestCase
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

    public function test_one_real_rkpd_program_indicator_node_abstains_from_unproven_regulatory_mapping(): void
    {
        $url = '/api/qa-planning-r70k/program-candidate';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'OTHER-LOCAL-QA']))
            ->getJson($url)->assertForbidden();

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson($url)->assertOk()
            ->assertJsonPath('schema', 'PI_R70K_TYPED_PLANNING_CROSSWALK_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('document.kind', 'RKPD')
            ->assertJsonPath('document.pdf_sha256', null)
            ->assertJsonPath('document.pdf_snapshot_status', 'NOT_CAPTURED_NO_HASH')
            ->assertJsonPath('planning_node.taxonomy_type', 'PROGRAM_NARRATIVE_WITH_PERFORMANCE_INDICATOR')
            ->assertJsonPath('planning_node.program_name_as_observed', 'program penyelenggaraan jalan')
            ->assertJsonPath('planning_node.official_program_code', null)
            ->assertJsonPath('planning_node.historical_observation_year', 2024)
            ->assertJsonPath('planning_node.historical_value_as_printed', '92,16')
            ->assertJsonPath('planning_node.planning_target_value', null)
            ->assertJsonPath('planning_node.source_printed_page', 'II-77')
            ->assertJsonPath('crosswalk.status', 'ABSTAIN')
            ->assertJsonPath('crosswalk.admitted_regulatory_relation', null)
            ->assertJsonPath('crosswalk.program_to_amendment_mapping', 'NOT_ESTABLISHED')
            ->assertJsonPath('interpretation.review_status', 'REVIEW_REQUIRED')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.production_admission', 'DENIED');
    }

    public function test_forged_program_code_or_2026_target_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $registry = json_decode(
            file_get_contents(resource_path('pi/official-planning-node-r70k.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $forgedCode = $registry;
        $forgedCode['node']['official_program_code'] = '2.03.02';
        $forgedCode['crosswalk']['status'] = 'MAPPING_CANDIDATE';
        $this->useRegistry($forgedCode);
        $this->getJson('/api/qa-planning-r70k/program-candidate')
            ->assertStatus(503)->assertJsonPath('code', 'PLANNING_NODE_SOURCE_UNAVAILABLE');

        $inventedTarget = $registry;
        $inventedTarget['node']['planning_target_value'] = '92,16';
        $this->useRegistry($inventedTarget);
        $this->getJson('/api/qa-planning-r70k/program-candidate')
            ->assertStatus(503)->assertJsonPath('code', 'PLANNING_NODE_SOURCE_UNAVAILABLE');
    }

    private function useRegistry(array $registry): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pi-r70k-');
        if ($file === false) {
            throw new \RuntimeException('Could not allocate R70K fixture.');
        }
        file_put_contents($file, json_encode($registry, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $file;
        config()->set('pi.planning_node.registry_path', $file);
    }
}
