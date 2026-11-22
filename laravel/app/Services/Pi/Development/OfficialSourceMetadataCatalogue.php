<?php

namespace App\Services\Pi\Development;

use JsonException;
use RuntimeException;

/**
 * R100: one bounded, OFFLINE index of genuine official-JDIH document metadata.
 *
 * An official index listing is NOT a downloaded, hash-pinned document, proof
 * of a 2026 budget amount, a reviewed legal conclusion or 2027 equivalence.
 * This service never performs HTTP, writes storage, or calls model providers.
 */
final class OfficialSourceMetadataCatalogue
{
    private const LANES = ['PLANNING', 'FISCAL', 'SECTOR', 'ACCOUNTABILITY'];
    private const METADATA_STATUS = 'OFFICIAL_JDIH_INDEXED';
    private const BYTES_STATUS = 'NOT_ACQUIRED_NOT_HASH_PINNED';

    public function __construct(private readonly string $path) {}

    /** @return array<string,mixed> */
    public function read(?string $lane = null): array
    {
        if ($lane !== null && ! in_array($lane, self::LANES, true)) {
            throw new RuntimeException('R100_INVALID_SOURCE_FILTER');
        }

        if (! is_file($this->path) || ! is_readable($this->path)
            || filesize($this->path) > 512 * 1024) {
            throw new RuntimeException('R100_OFFICIAL_SOURCE_REGISTRY_UNAVAILABLE');
        }

        $raw = file_get_contents($this->path);
        if (! is_string($raw) || $raw === '') {
            throw new RuntimeException('R100_OFFICIAL_SOURCE_REGISTRY_UNREADABLE');
        }

        try {
            $data = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('R100_OFFICIAL_SOURCE_REGISTRY_MALFORMED', 0, $exception);
        }

        $this->verify($data);

        $all = $data['documents'];
        $byLane = array_fill_keys(self::LANES, 0);
        foreach ($all as $document) {
            $byLane[$document['lane']]++;
        }

        $visible = $lane === null
            ? $all
            : array_values(array_filter(
                $all,
                static fn (array $document): bool => $document['lane'] === $lane
            ));

        return [
            'schema' => 'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1',
            'source_registry_sha256' => strtoupper(hash('sha256', $raw)),
            'snapshot_date' => $data['snapshot_date'],
            'jurisdiction_id' => $data['jurisdiction_id'],
            'case_id' => $data['case_id'],
            'filters' => ['lane' => $lane ?? 'ALL'],
            'counts' => [
                'indexed_official_metadata' => count($all),
                'displayed' => count($visible),
                'by_lane' => $byLane,
                'new_document_bytes_hash_verified' => 0,
                'new_subactivity_budget_amounts_verified' => 0,
                'reviewed_2026_to_2027_code_equivalences' => 0,
            ],
            'documents' => $visible,
            'evidence_gaps' => $data['known_evidence_gaps'],
            'specialized_readiness' => [
                ['id' => 'EVIDENCE', 'status' => 'SOURCE_DISCOVERY_ONLY', 'required_next' => 'Archive lawful primary bytes and validate SHA-256 and precise locators'],
                ['id' => 'REGULATORY', 'status' => 'LEGAL_REVIEW_REQUIRED', 'required_next' => 'Verify provision, amendments, and temporal scope per actual text'],
                ['id' => 'PERFORMANCE', 'status' => 'INDICATOR_EVIDENCE_MISSING', 'required_next' => 'Obtain reviewed indicator formula, unit, baseline and target versions'],
                ['id' => 'FISCAL', 'status' => 'NUMERIC_RECONCILIATION_BLOCKED', 'required_next' => 'Obtain versioned APBD annex, RKA, DPA and realization evidence'],
                ['id' => 'EVALUATION', 'status' => 'OUTCOME_EVIDENCE_MISSING', 'required_next' => 'Obtain monitoring, evaluation, APIP and BPK records separately'],
            ],
            'interpretation' => [
                'metadata_status' => self::METADATA_STATUS,
                'document_bytes_verification' => self::BYTES_STATUS,
                'metadata_rights' => 'PUBLIC_OFFICIAL_INDEX_METADATA_ONLY_RIGHTS_NOT_ASSESSED',
                'legal_harmonization' => 'NOT_ASSESSED',
                'numeric_comparison' => 'BLOCKED',
                'code_equivalence_2026_to_2027' => 'NOT_VERIFIED',
                'review_decision' => 'NO_ACTIVE_DECISION',
                'production_admission' => 'DENIED',
                'online_access' => 'NOT_ATTEMPTED_DURING_REQUEST',
            ],
        ];
    }

    /** @param mixed $data */
    private function verify(mixed $data): void
    {
        if (! is_array($data) || array_is_list($data)
            || ($data['schema'] ?? null) !== 'PI_R100_OFFICIAL_SOURCE_METADATA_CATALOGUE_V1'
            || ($data['snapshot_date'] ?? null) !== '2026-11-22'
            || ($data['jurisdiction_id'] ?? null) !== 'ID-DIY-YOG'
            || ($data['case_id'] ?? null) !== 'YOG-2026-PERSAMPAHAN'
            || ($data['metadata_status'] ?? null) !== self::METADATA_STATUS
            || ($data['document_bytes_verification'] ?? null) !== self::BYTES_STATUS
            || ($data['source_pdf_hashes_verified'] ?? null) !== 0
            || ($data['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($data['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($data['review_decision'] ?? null) !== 'NO_ACTIVE_DECISION'
            || ($data['production_admission'] ?? null) !== 'DENIED'
            || ($data['cross_year_subactivity_equivalence'] ?? null) !== 'NOT_VERIFIED'
            || ($data['no_network_fetch'] ?? null) !== true
            || ! is_array($data['documents'] ?? null) || ! array_is_list($data['documents'])
            || count($data['documents']) < 1 || count($data['documents']) > 100
            || ! is_array($data['known_evidence_gaps'] ?? null)) {
            throw new RuntimeException('R100_OFFICIAL_SOURCE_CONTRACT_INVALID');
        }

        $ids = [];
        $referenced = [];
        foreach ($data['documents'] as $record) {
            if (! is_array($record)
                || ! is_string($record['id'] ?? null)
                || preg_match('/\AYOG-[A-Z0-9-]{3,100}\z/D', $record['id']) !== 1
                || isset($ids[$record['id']])
                || ! in_array($record['lane'] ?? null, self::LANES, true)
                || ! in_array($record['instrument'] ?? null, ['PERDA', 'PERWAL', 'KEPWAL'], true)
                || ! is_int($record['number'] ?? null)
                || $record['number'] < 1
                || ! is_int($record['year'] ?? null)
                || $record['year'] < 2020 || $record['year'] > 2026
                || ! is_string($record['title'] ?? null)
                || trim($record['title']) === '' || strlen($record['title']) > 300
                || ! is_string($record['period'] ?? null) || trim($record['period']) === ''
                || ! is_string($record['kind'] ?? null) || trim($record['kind']) === ''
                || ! $this->officialLink($record['official_url'] ?? null)
                || ! is_string($record['locator'] ?? null) || trim($record['locator']) === ''
                || ($record['source_metadata_status'] ?? null) !== self::METADATA_STATUS
                || ($record['document_bytes_status'] ?? null) !== self::BYTES_STATUS
                || ($record['document_sha256'] ?? 'INVALID') !== null
                || ($record['numeric_amount'] ?? 'INVALID') !== null
                || ($record['case_program_equivalence'] ?? null) !== 'NOT_VERIFIED'
                || ($record['legal_validity'] ?? null) !== 'NOT_ASSESSED') {
                throw new RuntimeException('R100_OFFICIAL_SOURCE_ENTRY_INVALID');
            }
            $ids[$record['id']] = true;
            if (($record['revision_of'] ?? null) !== null) {
                if (! is_string($record['revision_of'])
                    || $record['revision_of'] === $record['id']) {
                    throw new RuntimeException('R100_OFFICIAL_SOURCE_REVISION_INVALID');
                }
                $referenced[] = $record['revision_of'];
            }
        }
        foreach ($referenced as $id) {
            if (! isset($ids[$id])) {
                throw new RuntimeException('R100_OFFICIAL_SOURCE_UNKNOWN_BASE_VERSION');
            }
        }

        foreach ($data['known_evidence_gaps'] as $gap) {
            if (! is_string($gap)
                || preg_match('/\A[A-Z0-9_]{6,130}\z/D', $gap) !== 1) {
                throw new RuntimeException('R100_OFFICIAL_SOURCE_GAP_INVALID');
            }
        }
    }

    private function officialLink(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) > 300) {
            return false;
        }
        $parsed = parse_url($value);
        if (! is_array($parsed)
            || ($parsed['scheme'] ?? null) !== 'https'
            || ($parsed['host'] ?? null) !== 'jdih.jogjakota.go.id'
            || isset($parsed['user']) || isset($parsed['pass'])
            || isset($parsed['port']) || isset($parsed['query'])
            || isset($parsed['fragment'])) {
            return false;
        }
        $path = (string) ($parsed['path'] ?? '');

        return preg_match('~\A/home/produk_hukum/detail/[0-9]{1,8}\z~D', $path) === 1
            || preg_match('~\A/asset/naskah/[a-zA-Z0-9._-]+\.pdf\z~D', $path) === 1;
    }
}
