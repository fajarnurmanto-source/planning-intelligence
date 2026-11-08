<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class PlanningLineageTest extends TestCase
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

    public function test_official_rpjmd_rkpd_lineage_is_tenant_scoped_and_review_only(): void
    {
        $url = '/api/qa-planning-r70j/lineage-candidates';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->getJson($url)->assertForbidden();

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']))
            ->getJson($url)->assertOk()
            ->assertJsonPath('schema', 'PI_R70J_DOCUMENT_LINEAGE_READ_MODEL_V1')
            ->assertJsonPath('tenant_id', 'ID-DIY-YOG')
            ->assertJsonPath('chronology.rkpd_issued_on', '2025-07-07')
            ->assertJsonPath('chronology.rpjmd_final_issued_on', '2025-08-15')
            ->assertJsonPath('chronology.rkpd_predates_rpjmd_final', true)
            ->assertJsonPath('chronology.interpretation', 'VERSION_REVIEW_REQUIRED_NOT_VIOLATION')
            ->assertJsonPath('source_integrity', 'REGISTRY_SHA256_ONLY_PDF_NOT_PINNED')
            ->assertJsonPath('documents.0.pdf_sha256', null)
            ->assertJsonPath('documents.1.pdf_snapshot_state', 'NOT_CAPTURED_NO_HASH')
            ->assertJsonPath('relations.1.mapping_status', 'MAPPING_CANDIDATE')
            ->assertJsonPath('interpretation.review_status', 'REVIEW_REQUIRED')
            ->assertJsonPath('interpretation.legal_harmonization', 'NOT_ASSESSED')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.production_admission', 'DENIED')
            ->assertJsonCount(2, 'documents')
            ->assertJsonCount(2, 'relations');
    }

    public function test_unpinned_forged_pdf_hash_or_unofficial_source_rejects_the_whole_read_model(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $registry = json_decode(
            file_get_contents(resource_path('pi/official-planning-lineage-r70j.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $forgedHash = $registry;
        $forgedHash['documents'][0]['pdf_sha256'] = str_repeat('a', 64);
        $this->useRegistry($forgedHash);
        $this->getJson('/api/qa-planning-r70j/lineage-candidates')->assertStatus(503)
            ->assertJsonPath('code', 'PLANNING_LINEAGE_SOURCE_UNAVAILABLE');

        $unofficial = $registry;
        $unofficial['documents'][1]['metadata_url'] = 'https://unverified.example/claim';
        $this->useRegistry($unofficial);
        $this->getJson('/api/qa-planning-r70j/lineage-candidates')->assertStatus(503)
            ->assertJsonPath('code', 'PLANNING_LINEAGE_SOURCE_UNAVAILABLE');
    }

    private function useRegistry(array $registry): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pi-r70j-');
        if ($file === false) {
            throw new \RuntimeException('Unable to write temporary R70J fixture.');
        }
        file_put_contents($file, json_encode($registry, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $file;
        config()->set('pi.planning_lineage.registry_path', $file);
    }
}
