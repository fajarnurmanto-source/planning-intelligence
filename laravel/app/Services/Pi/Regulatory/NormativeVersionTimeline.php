<?php

namespace App\Services\Pi\Regulatory;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Evidence-bound, as-of-date version timeline. This is NOT a determination of
 * legal validity, nor a claim that the amendment census is exhaustive.
 */
final class NormativeVersionTimeline
{
    /**
     * @param list<array<string, mixed>> $instruments
     * @param list<array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public function resolve(array $instruments, array $events, string $asOfDate): array
    {
        $this->assertDate($asOfDate);
        if (count($instruments) < 1 || count($instruments) > 100 || count($events) > 200) {
            throw new InvalidArgumentException('Version timeline size outside safe bounds.');
        }

        $known = [];
        $states = [];
        $incomplete = false;
        foreach ($instruments as $instrument) {
            $id = $instrument['id'] ?? null;
            $effective = $instrument['effective_date'] ?? null;
            $census = $instrument['amendment_census'] ?? null;
            if (!is_string($id) || !preg_match('/^[A-Z0-9-]{4,100}$/', $id)
                || isset($known[$id])
                || !in_array($census, ['NOT_EXHAUSTIVELY_VERIFIED', 'OFFICIAL_CENSUS_VERIFIED_COMPLETE'], true)
                || ($effective !== null && !is_string($effective))) {
                throw new InvalidArgumentException('Untrusted regulation identity or census state.');
            }
            if (is_string($effective)) {
                $this->assertDate($effective);
            }
            $known[$id] = true;
            $dateState = $effective === null ? 'START_DATE_UNVERIFIED' :
                ($effective > $asOfDate ? 'NOT_YET_EFFECTIVE_ON_CASE_DATE' : 'EFFECTIVE_DATE_NOT_AFTER_CASE');
            if ($census !== 'OFFICIAL_CENSUS_VERIFIED_COMPLETE' || $effective === null) {
                $incomplete = true;
            }
            $states[$id] = [
                'instrument_id' => $id,
                'date_state' => $dateState,
                'amendment_census' => $census,
                'provision_events_effective' => 0,
                'provision_events_future' => 0,
                'change_scopes' => [],
            ];
        }

        $seenEvents = [];
        $decisions = [];
        foreach ($events as $event) {
            $id = $event['id'] ?? null;
            $target = $event['target_instrument_id'] ?? null;
            $type = $event['relation'] ?? null;
            $effectiveOn = $event['effective_on'] ?? null;
            $source = $event['official_source_url'] ?? null;
            $locator = $event['source_provision_locator'] ?? null;
            $evidenceSha = $event['source_sha256'] ?? null;
            $affected = $event['affected_provision_ids'] ?? null;
            if (!is_string($id) || !preg_match('/^[A-Z0-9-]{4,100}$/', $id)
                || isset($seenEvents[$id])
                || !is_string($target) || !isset($known[$target])
                || !in_array($type, ['AMENDS', 'REVOKES', 'REPLACES', 'TRANSITIONAL'], true)
                || !is_string($effectiveOn) || !is_string($source)
                || !is_string($locator) || trim($locator) === ''
                || !is_string($evidenceSha) || !preg_match('/^[A-Fa-f0-9]{64}$/', $evidenceSha)
                || !is_array($affected) || array_is_list($affected) === false || count($affected) < 1
                || count($affected) > 100 || ($event['evidence_status'] ?? null) !== 'OFFICIAL_SOURCE_SNAPSHOT_VERIFIED') {
                throw new InvalidArgumentException('Change event lacks bound primary evidence.');
            }
            $this->assertDate($effectiveOn);
            $parsed = parse_url($source);
            if (!is_array($parsed) || ($parsed['scheme'] ?? null) !== 'https'
                || !in_array(strtolower((string) ($parsed['host'] ?? '')), [
                    'peraturan.go.id', 'www.peraturan.go.id', 'peraturan.bpk.go.id',
                ], true)) {
                throw new InvalidArgumentException('Change source is not an allowlisted official host.');
            }
            foreach ($affected as $provisionId) {
                if (!is_string($provisionId) || !preg_match('/^[A-Z0-9-]{4,100}$/', $provisionId)) {
                    throw new InvalidArgumentException('Change scope is invalid.');
                }
            }
            $seenEvents[$id] = true;
            $hasTakenEffect = $effectiveOn <= $asOfDate;
            $states[$target][$hasTakenEffect ? 'provision_events_effective' : 'provision_events_future']++;
            if ($hasTakenEffect) {
                foreach ($affected as $provisionId) {
                    $states[$target]['change_scopes'][$provisionId][] = $type;
                }
            }
            $decisions[] = [
                'id' => $id,
                'target_instrument_id' => $target,
                'relation' => $type,
                'effective_on' => $effectiveOn,
                'affected_provision_ids' => array_values(array_unique($affected)),
                'temporal_state' => $hasTakenEffect ? 'CHANGE_EFFECTIVE_FOR_SCOPED_PROVISIONS' : 'FUTURE_CHANGE_NOT_YET_EFFECTIVE',
                'source_provision_locator' => $locator,
                'source_sha256' => strtoupper($evidenceSha),
            ];
        }

        return [
            'as_of_date' => $asOfDate,
            'status' => $incomplete ? 'AMENDMENT_CHAIN_INCOMPLETE' : 'EVIDENCE_BOUND_VERSION_TRACE',
            'instrument_states' => array_values($states),
            'change_events' => $decisions,
            'verified_change_event_count' => count($decisions),
            'no_events_means_no_changes' => false,
            'legal_conclusion' => 'NOT_ASSESSED',
            'review_status' => 'REVIEW_REQUIRED',
        ];
    }

    private function assertDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Date must be a real ISO calendar day.');
        }
    }
}
