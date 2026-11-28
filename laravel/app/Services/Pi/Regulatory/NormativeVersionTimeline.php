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

    /**
     * Period selection is not a validity finding. The observation cutoff is
     * separate from case date and target year; no implicit supersession.
     */
    public function resolveSeries(array $series,array $members,int $targetYear,string $caseDate,string $knowledgeDate): array
    {
        $this->assertDate($caseDate);
        $this->assertDate($knowledgeDate);
        if ($targetYear<1900 || $targetYear>2200 || count($members)>500
            || !is_string($series['id']??null) || !preg_match('/^[A-Z0-9:-]{4,160}$/D',$series['id'])
            || !in_array($series['cadence']??null,['ANNUAL','MULTI_YEAR','IRREGULAR'],true)) {
            throw new InvalidArgumentException('PI_REG_SERIES_INPUT_HOLD');
        }
        $registry=new RegulatoryTaxonomyRegistry();
        $records=[];$seen=[];$eligible=[];$unknown=[];
        foreach ($members as $member) {
            $id=$registry->canonicalIdentity($member);
            if (isset($seen[$id]) || ($member['series_id']??null)!==$series['id']
                || !is_int($member['target_start_year']??null) || !is_int($member['target_end_year']??null)
                || $member['target_start_year']<1900 || $member['target_end_year']>2200
                || $member['target_start_year']>$member['target_end_year']
                || ($series['cadence']==='ANNUAL' && $member['target_start_year']!==$member['target_end_year'])
                || !$registry->officialUrl($member['source_url']??null)
                || !is_string($member['source_locator']??null) || trim($member['source_locator'])==='') {
                throw new InvalidArgumentException('PI_REG_SERIES_MEMBER_HOLD');
            }
            $seen[$id]=true;
            foreach (['observed_on','effective_on','repealed_on'] as $date) {
                $v=$member[$date]??null;
                if ($v!==null) {
                    if (!is_string($v)) throw new InvalidArgumentException('PI_REG_SERIES_DATE_HOLD');
                    $this->assertDate($v);
                }
            }
            $observed=$member['observed_on']??null;
            $effective=$member['effective_on']??null;
            $repealed=$member['repealed_on']??null;
            if ($effective!==null && $repealed!==null && $repealed<$effective) throw new InvalidArgumentException('PI_REG_SERIES_INTERVAL_HOLD');
            $matches=$member['target_start_year']<=$targetYear && $targetYear<=$member['target_end_year'];
            $state=match(true) {
                !$matches=>'OTHER_TARGET_PERIOD',
                $observed===null=>'OBSERVATION_DATE_UNKNOWN',
                $observed>$knowledgeDate=>'NOT_KNOWN_AT_CUTOFF',
                $effective===null=>'EFFECTIVE_DATE_UNKNOWN',
                $effective>$caseDate=>'NOT_YET_EFFECTIVE',
                $repealed!==null && $repealed<=$caseDate=>'REPEAL_DATE_NOT_AFTER_CASE',
                default=>'TEMPORAL_CANDIDATE_REVIEW_REQUIRED'
            };
            if ($state==='TEMPORAL_CANDIDATE_REVIEW_REQUIRED') $eligible[]=$id;
            if (in_array($state,['OBSERVATION_DATE_UNKNOWN','EFFECTIVE_DATE_UNKNOWN'],true)) $unknown[]=$id;
            $records[]=['canonical_id'=>$id,'state'=>$state,'target_start_year'=>$member['target_start_year'],'target_end_year'=>$member['target_end_year'],'observed_on'=>$observed,'effective_on'=>$effective,'repealed_on'=>$repealed,'source_url'=>$member['source_url'],'source_locator'=>$member['source_locator']];
        }
        $state=match(true){
            count($eligible)>1=>'MULTIPLE_CANDIDATES_HOLD',
            count($unknown)>0=>'UNKNOWN_TEMPORAL_EVIDENCE_HOLD',
            count($eligible)===1=>'ONE_CANDIDATE_REVIEW_REQUIRED',
            default=>'DISCOVERY_REQUIRED'
        };
        return ['schema'=>'PI_REGULATORY_SERIES_SELECTION_V1','series_id'=>$series['id'],'target_year'=>$targetYear,'case_date'=>$caseDate,'knowledge_date'=>$knowledgeDate,'state'=>$state,'candidate_ids'=>$eligible,'unknown_ids'=>$unknown,'records'=>$records,'implicit_amendments'=>0,'implicit_repeals'=>0,'census_complete'=>false,'legal_conclusion'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
    }

    private function assertDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Date must be a real ISO calendar day.');
        }
    }
}
