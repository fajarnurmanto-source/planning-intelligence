<?php

namespace Tests\Unit;

use App\Services\Pi\Taxonomy\AffairFirstNomenclature;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PiAffairsNomenclatureTest extends TestCase
{
    private function subject(): AffairFirstNomenclature
    {
        return new AffairFirstNomenclature(dirname(__DIR__, 2).'/resources/pi/affairs-nomenclature-seed-r87.json');
    }

    public function test_all_statutory_affairs_present_without_unearned_national_codes(): void
    {
        $data = $this->subject()->catalogue();
        self::assertCount(32, $data['affairs']);
        self::assertSame(6, count(array_filter($data['affairs'], fn ($a) => $a['group'] === 'BASIC')));
        self::assertSame(18, count(array_filter($data['affairs'], fn ($a) => $a['group'] === 'NON_BASIC')));
        self::assertSame(8, count(array_filter($data['affairs'], fn ($a) => $a['group'] === 'OPTIONAL')));
        self::assertSame(0, $data['coverage']['national_master_codes_verified']);
        self::assertFalse($data['coverage']['national_nomenclature_complete']);
    }

    public function test_real_waste_code_is_a_local_observation_not_a_legal_verdict(): void
    {
        $result = $this->subject()->classify('2.11.11.2.01.0004', 2026);
        self::assertSame('EXACT_LOCAL_OBSERVATION', $result['status']);
        self::assertSame('CONC-NON_BASIC-05', $result['match']['affair_id']);
        self::assertFalse($result['national_master_verified']);
        self::assertSame('NOT_ASSESSED', $result['legal_applicability']);
        self::assertCount(1, $result['sectoral_discovery_candidates']);
    }

    public function test_unmapped_prefix_only_and_other_year_do_not_promote(): void
    {
        self::assertSame('ABSTAIN_UNMAPPED', $this->subject()->classify('1.99.99', 2026)['status']);
        self::assertSame('PARENT_PREFIX_CANDIDATE', $this->subject()->classify('2.11.11.2.01.9999', 2026)['status']);
        self::assertSame('YEAR_SCOPE_REVIEW_REQUIRED', $this->subject()->classify('2.11.11.2.01.0004', 2027)['status']);
    }

    public function test_incremental_batch_reports_collisions_without_writing(): void
    {
        $record = [
            'code' => '2.11.11.2.01.0004', 'label' => 'Candidate', 'version' => 'LOCAL_YOG_2026_OBSERVED',
            'year' => 2026, 'source_url' => 'https://jdih.jogjakota.go.id/asset/naskah/2026pw3405004.pdf',
            'locator' => 'QA unit case',
        ];
        $result = $this->subject()->stageBatch([$record]);
        self::assertSame('COLLISION_REVIEW_REQUIRED', $result['entries'][0]['status']);
        self::assertFalse($result['persisted']);
        self::assertFalse($result['promoted']);
    }

    public function test_unsafe_candidate_source_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->subject()->stageBatch([[
            'code' => '2.11.11.2.01.0099', 'label' => 'Invalid', 'version' => 'QA',
            'year' => 2026, 'source_url' => 'https://evil.example/unverified',
            'locator' => 'QA',
        ]]);
    }
}
