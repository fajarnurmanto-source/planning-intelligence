<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pi\Regulatory\R82NationalEvidencePipeline;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class R83NationalPdfReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_immutable_national_pdf_review_enforces_tenant_and_exact_ids(): void
    {
        Http::preventStrayRequests();
        $url = '/api/qa-regulatory-r83/national-pdf';
        $this->get($url.'?source_id=PP-81-2012')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['tenant_id' => 'QA-OTHER']))
            ->get($url.'?source_id=PP-81-2012')->assertForbidden();

        $this->actingAs(User::factory()->create(['tenant_id' => 'ID-DIY-YOG']));
        $this->get($url.'?source_id=../../.env')->assertNotFound();
        $this->get($url.'?source_id=PP-81-2012.pdf')->assertNotFound();
        $this->get($url.'?source_id[]=PP-81-2012')->assertNotFound();
        $this->get($url)->assertNotFound();

        $pipeline = app(R82NationalEvidencePipeline::class);
        $realSourcesPresent = false;
        try {
            $info = app(R82NationalSourceVerifier::class)
                ->verify($pipeline->snapshotDirectory());
            $realSourcesPresent = count($info['documents']) === 2;
        } catch (RuntimeException $error) {
            // Clean CI does not have operator-owned R81 snapshots.
        }
        foreach (['PP-81-2012', 'UU-18-2008'] as $id) {
            $response = $this->get($url.'?source_id='.$id);
            if ($realSourcesPresent) {
                $response->assertOk()
                    ->assertHeader('content-type', 'application/pdf')
                    ->assertHeader('x-content-type-options', 'nosniff')
                    ->assertHeader('x-frame-options', 'SAMEORIGIN')
                    ->assertHeader('referrer-policy', 'no-referrer');
                $cacheControl = (string) $response->headers->get('cache-control');
                $this->assertStringContainsString('private', $cacheControl);
                $this->assertStringContainsString('no-store', $cacheControl);
                $this->assertStringContainsString('max-age=0', $cacheControl);
                $this->assertStringNotContainsString('public', $cacheControl);
                $this->assertStringContainsString('inline',
                    $response->headers->get('content-disposition'));
            } else {
                $response->assertStatus(503)
                    ->assertJsonPath('status', 'ABSTAIN')
                    ->assertJsonPath('production_admission', 'DENIED');
            }
        }
        Http::assertNothingSent();
    }
}
