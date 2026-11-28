<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Development\OfficialSourceMetadataCatalogue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class R100OfficialSourceMetadataTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = '/api/qa-superior-r100/official-sources';

    public function test_18_indexed_official_records_are_read_only_authenticated_and_fail_closed(): void
    {
        Http::preventStrayRequests();

        $this->getJson(self::URL)->assertUnauthorized();

        $this->actingAs(User::factory()->create([
            'tenant_id' => 'WRONG-TENANT',
            'role' => 'institution_admin',
        ]))->getJson(self::URL)->assertForbidden();

        $this->actingAs(User::factory()->create([
            'tenant_id' => 'ID-DIY-YOG',
            'role' => 'institution_admin',
        ]));

        $response = $this->getJson(self::URL)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema', 'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1')
            ->assertJsonPath('tenant_id', config('pi.tenant_id'))
            ->assertJsonPath('jurisdiction_id', 'ID-DIY-YOG')
            ->assertJsonPath('counts.indexed_official_metadata', 18)
            ->assertJsonPath('counts.displayed', 18)
            ->assertJsonPath('counts.by_lane.PLANNING', 9)
            ->assertJsonPath('counts.by_lane.FISCAL', 7)
            ->assertJsonPath('counts.by_lane.SECTOR', 1)
            ->assertJsonPath('counts.by_lane.ACCOUNTABILITY', 1)
            ->assertJsonPath('counts.new_document_bytes_hash_verified', 0)
            ->assertJsonPath('counts.new_subactivity_budget_amounts_verified', 0)
            ->assertJsonPath('counts.reviewed_2026_to_2027_code_equivalences', 0)
            ->assertJsonPath('interpretation.document_bytes_verification', 'NOT_ACQUIRED_NOT_HASH_PINNED')
            ->assertJsonPath('interpretation.numeric_comparison', 'BLOCKED')
            ->assertJsonPath('interpretation.code_equivalence_2026_to_2027', 'NOT_VERIFIED')
            ->assertJsonPath('interpretation.online_access', 'NOT_ATTEMPTED_DURING_REQUEST')
            ->assertJsonPath('interpretation.production_admission', 'DENIED')
            ->assertJsonCount(18, 'documents')->assertJsonCount(5, 'specialized_readiness');

        $this->assertMatchesRegularExpression('/\A[A-F0-9]{64}\z/', $response->json('source_registry_sha256'));
        foreach ($response->json('documents') as $item) {
            $this->assertSame('OFFICIAL_JDIH_INDEXED', $item['source_metadata_status']);
            $this->assertSame('NOT_ACQUIRED_NOT_HASH_PINNED', $item['document_bytes_status']);
            $this->assertNull($item['document_sha256']);
            $this->assertNull($item['numeric_amount']);
            $this->assertSame('NOT_VERIFIED', $item['case_program_equivalence']);
            $this->assertSame('NOT_ASSESSED', $item['legal_validity']);
            $this->assertStringStartsWith('https://jdih.jogjakota.go.id/', $item['official_url']);
        }

        $this->getJson(self::URL.'?lane=FISCAL')->assertOk()
            ->assertJsonCount(7, 'documents')
            ->assertJsonPath('counts.indexed_official_metadata', 18)
            ->assertJsonPath('counts.displayed', 7);

        $this->getJson(self::URL.'?lane=PLANNING')->assertOk()
            ->assertJsonCount(9, 'documents');
        $this->getJson(self::URL.'?lane=SECTOR')->assertOk()
            ->assertJsonCount(1, 'documents');
        $this->getJson(self::URL.'?lane=ACCOUNTABILITY')->assertOk()
            ->assertJsonCount(1, 'documents');
        $this->getJson(self::URL.'?lane=ALL')->assertUnprocessable();

        $this->assertDatabaseCount('regulatory_evidence_reviews', 0);
        Http::assertNothingSent();
    }

    public function test_missing_invalid_or_tampered_index_never_becomes_admitted_data(): void
    {
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create([
            'tenant_id' => 'ID-DIY-YOG',
            'role' => 'institution_admin',
        ]));

        config()->set('pi.official_sources.registry_path', storage_path('nonexistent-r100-index.json'));
        $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('status', 'ABSTAIN')
            ->assertJsonPath('code', 'OFFICIAL_SOURCE_METADATA_UNAVAILABLE')
            ->assertJsonMissingPath('documents');

        $source = json_decode((string) file_get_contents(resource_path('pi/official-source-metadata-r100.json')), true);
        $temporary = tempnam(sys_get_temp_dir(), 'pi-r100-');
        $this->assertIsString($temporary);

        try {
            $index = $source;
            $index['documents'][0]['numeric_amount'] = 500000000;
            file_put_contents($temporary, json_encode($index));
            $this->assertThrowsRuntimeForPath($temporary);
            $this->getJson(self::URL)->assertStatus(503)->assertJsonMissingPath('documents');

            $index = $source;
            $index['documents'][0]['official_url'] = 'https://attacker.example/replace';
            file_put_contents($temporary, json_encode($index));
            $this->assertThrowsRuntimeForPath($temporary);

            $index = $source;
            $index['documents'][0]['document_sha256'] = str_repeat('A', 64);
            file_put_contents($temporary, json_encode($index));
            $this->assertThrowsRuntimeForPath($temporary);

            $index = $source;
            $index['documents'][] = $index['documents'][0];
            file_put_contents($temporary, json_encode($index));
            $this->assertThrowsRuntimeForPath($temporary);

            Http::assertNothingSent();
        } finally {
            @unlink($temporary);
        }
    }

    private function assertThrowsRuntimeForPath(string $path): void
    {
        config()->set('pi.official_sources.registry_path', $path);
        try {
            (new OfficialSourceMetadataCatalogue($path))->read();
            $this->fail('Tampered source was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('R100_', $exception->getMessage());
        }
    }
}
