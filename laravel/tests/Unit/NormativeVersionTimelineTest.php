<?php

namespace Tests\Unit;

use App\Services\Pi\Regulatory\NormativeVersionTimeline;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NormativeVersionTimelineTest extends TestCase
{
    public function test_incomplete_official_census_and_unknown_start_date_never_become_verified(): void
    {
        $instruments = [
            ['id' => 'IDN-PERMENDAGRI-86-2017', 'effective_date' => null,
                'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED'],
            ['id' => 'IDN-PERMENDAGRI-10-2025', 'effective_date' => '2025-06-23',
                'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED'],
        ];
        $timeline = (new NormativeVersionTimeline())->resolve($instruments, [], '2026-11-08');

        self::assertSame('AMENDMENT_CHAIN_INCOMPLETE', $timeline['status']);
        self::assertSame('START_DATE_UNVERIFIED', $timeline['instrument_states'][0]['date_state']);
        self::assertSame('EFFECTIVE_DATE_NOT_AFTER_CASE', $timeline['instrument_states'][1]['date_state']);
        self::assertSame(0, $timeline['verified_change_event_count']);
        self::assertFalse($timeline['no_events_means_no_changes']);
        self::assertSame('NOT_ASSESSED', $timeline['legal_conclusion']);
    }

    public function test_verified_partial_amendment_is_future_then_effective_only_for_its_provision(): void
    {
        $instrument = [[
            'id' => 'IDN-EXAMPLE-2025',
            'effective_date' => '2025-01-01',
            'amendment_census' => 'OFFICIAL_CENSUS_VERIFIED_COMPLETE',
        ]];
        $event = [[
            'id' => 'AMENDMENT-2026-01',
            'target_instrument_id' => 'IDN-EXAMPLE-2025',
            'relation' => 'AMENDS',
            'effective_on' => '2026-07-01',
            'official_source_url' => 'https://peraturan.go.id/files/example.pdf',
            'source_provision_locator' => 'Pasal I angka 1',
            'source_sha256' => str_repeat('A', 64),
            'affected_provision_ids' => ['ART-5-CLAUSE-2'],
            'evidence_status' => 'OFFICIAL_SOURCE_SNAPSHOT_VERIFIED',
        ]];

        $resolver = new NormativeVersionTimeline();
        $before = $resolver->resolve($instrument, $event, '2026-06-30');
        self::assertSame('EVIDENCE_BOUND_VERSION_TRACE', $before['status']);
        self::assertSame('FUTURE_CHANGE_NOT_YET_EFFECTIVE', $before['change_events'][0]['temporal_state']);
        self::assertSame(0, $before['instrument_states'][0]['provision_events_effective']);
        self::assertSame([], $before['instrument_states'][0]['change_scopes']);

        $after = $resolver->resolve($instrument, $event, '2026-07-01');
        self::assertSame('CHANGE_EFFECTIVE_FOR_SCOPED_PROVISIONS', $after['change_events'][0]['temporal_state']);
        self::assertSame(['AMENDS'], $after['instrument_states'][0]['change_scopes']['ART-5-CLAUSE-2']);
        self::assertSame(1, $after['instrument_states'][0]['provision_events_effective']);
        self::assertSame('NOT_ASSESSED', $after['legal_conclusion']);
    }

    public function test_provision_scoped_revocation_does_not_erase_other_provisions(): void
    {
        $instrument = [[
            'id' => 'IDN-EXAMPLE-2025',
            'effective_date' => '2025-01-01',
            'amendment_census' => 'OFFICIAL_CENSUS_VERIFIED_COMPLETE',
        ]];
        $event = [[
            'id' => 'REVOCATION-2026-01',
            'target_instrument_id' => 'IDN-EXAMPLE-2025',
            'relation' => 'REVOKES',
            'effective_on' => '2026-08-01',
            'official_source_url' => 'https://peraturan.go.id/files/example.pdf',
            'source_provision_locator' => 'Pasal II',
            'source_sha256' => str_repeat('C', 64),
            'affected_provision_ids' => ['ART-2'],
            'evidence_status' => 'OFFICIAL_SOURCE_SNAPSHOT_VERIFIED',
        ]];

        $result = (new NormativeVersionTimeline())->resolve($instrument, $event, '2026-11-08');
        self::assertSame(['ART-2' => ['REVOKES']], $result['instrument_states'][0]['change_scopes']);
        self::assertSame('REVIEW_REQUIRED', $result['review_status']);
        self::assertSame('NOT_ASSESSED', $result['legal_conclusion']);
    }

    public function test_missing_source_hash_official_domain_or_valid_date_fails_closed(): void
    {
        $instrument = [[
            'id' => 'IDN-EXAMPLE-2025',
            'effective_date' => '2025-01-01',
            'amendment_census' => 'OFFICIAL_CENSUS_VERIFIED_COMPLETE',
        ]];
        $event = [
            'id' => 'AMENDMENT-2026-01',
            'target_instrument_id' => 'IDN-EXAMPLE-2025',
            'relation' => 'AMENDS',
            'effective_on' => '2026-07-01',
            'official_source_url' => 'https://peraturan.go.id/files/example.pdf',
            'source_provision_locator' => 'Pasal I',
            'source_sha256' => str_repeat('B', 64),
            'affected_provision_ids' => ['ART-5'],
            'evidence_status' => 'OFFICIAL_SOURCE_SNAPSHOT_VERIFIED',
        ];
        $resolver = new NormativeVersionTimeline();

        foreach ([
            ['source_sha256', 'UNPINNED'],
            ['official_source_url', 'https://example.org/fake.pdf'],
            ['effective_on', '2026-02-30'],
            ['evidence_status', 'NOT_VERIFIED'],
        ] as [$key, $invalid]) {
            $broken = $event;
            $broken[$key] = $invalid;
            try {
                $resolver->resolve($instrument, [$broken], '2026-11-08');
                self::fail('Untrusted event was admitted: '.$key);
            } catch (InvalidArgumentException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }
}
