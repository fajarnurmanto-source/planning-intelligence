<?php

namespace App\Services\Pi\Taxonomy;

use JsonException;
use RuntimeException;

/**
 * Read-only affairs-first classifier; contains no network, database, promotion or legal verdict.
 */
final class AffairFirstNomenclature
{
    public function __construct(private readonly string $path) {}

    /** @return array<string, mixed> */
    public function catalogue(): array
    {
        $data = $this->load();
        return [
            'schema' => 'PI_AFFAIRS_CATALOGUE_V1',
            'affairs' => $data['affairs'],
            'nomenclature' => $data['nomenclature'],
            'sources' => $data['sources'],
            'coverage' => [
                'concurrent_affair_categories' => 32,
                'local_observations' => count($data['nomenclature']),
                'national_master_codes_verified' => 0,
                'national_nomenclature_complete' => false,
            ],
            'gates' => $data['gates'],
        ];
    }

    /** @return array<string, mixed> */
    public function classify(string $code, int $year): array
    {
        if (! preg_match('/\A\d{1,2}(?:\.\d{1,4}){1,4}\z/', $code)
            || $year < 2000 || $year > 2100) {
            throw new RuntimeException('INVALID_CODE_OR_YEAR');
        }
        $data = $this->load();
        $exact = null;
        $prefix = null;
        foreach ($data['nomenclature'] as $item) {
            if ($item['code'] === $code) {
                $exact = $item;
                break;
            }
            if (str_starts_with($code, $item['code'].'.')
                && ($prefix === null || strlen($item['code']) > strlen($prefix['code']))) {
                $prefix = $item;
            }
        }
        $match = $exact ?? $prefix;
        $status = $exact !== null
            ? ($exact['year'] === $year ? 'EXACT_LOCAL_OBSERVATION' : 'YEAR_SCOPE_REVIEW_REQUIRED')
            : ($prefix !== null ? 'PARENT_PREFIX_CANDIDATE' : 'ABSTAIN_UNMAPPED');
        $sectoral = [];
        if ($match !== null) {
            foreach ($data['sectoral_triggers'] as $trigger) {
                if ($trigger['affair_id'] === $match['affair_id']
                    && ($code === $trigger['code_prefix']
                        || str_starts_with($code, $trigger['code_prefix'].'.'))) {
                    $sectoral[] = $trigger;
                }
            }
        }

        return [
            'schema' => 'PI_AFFAIR_FIRST_CLASSIFICATION_V1',
            'code' => $code, 'document_year' => $year, 'status' => $status,
            'match_type' => $exact !== null ? 'EXACT' : ($prefix !== null ? 'PREFIX' : 'NONE'),
            'match' => $match, 'sectoral_discovery_candidates' => $sectoral,
            'national_master_verified' => false, 'authority' => 'NOT_ASSESSED',
            'legal_applicability' => 'NOT_ASSESSED', 'production_admission' => 'DENIED',
        ];
    }

    /**
     * Future batches are validated and reported, never persisted or promoted.
     * @param list<array<string, mixed>> $batch
     * @return array<string, mixed>
     */
    public function stageBatch(array $batch): array
    {
        $data = $this->load();
        $known = [];
        foreach ($data['nomenclature'] as $item) {
            $known[$item['version'].'|'.$item['year'].'|'.$item['code']] = true;
        }
        $results = [];
        foreach ($batch as $candidate) {
            if (! is_array($candidate)
                || ! is_string($candidate['code'] ?? null)
                || ! preg_match('/\A\d{1,2}(?:\.\d{1,4}){1,4}\z/', $candidate['code'])
                || ! is_string($candidate['label'] ?? null) || trim($candidate['label']) === ''
                || ! is_string($candidate['version'] ?? null) || trim($candidate['version']) === ''
                || ! is_int($candidate['year'] ?? null) || $candidate['year'] < 2000 || $candidate['year'] > 2100
                || ! is_string($candidate['locator'] ?? null) || trim($candidate['locator']) === ''
                || ! $this->safeSource($candidate['source_url'] ?? null)) {
                throw new RuntimeException('NOMENCLATURE_BATCH_INVALID');
            }
            $key = $candidate['version'].'|'.$candidate['year'].'|'.$candidate['code'];
            $results[] = [
                'code' => $candidate['code'],
                'status' => isset($known[$key]) ? 'COLLISION_REVIEW_REQUIRED' : 'STAGED_UNVERIFIED',
                'source_url' => $candidate['source_url'],
                'locator' => $candidate['locator'],
            ];
            $known[$key] = true;
        }

        return [
            'schema' => 'PI_NOMENCLATURE_STAGING_V1', 'entries' => $results,
            'persisted' => false, 'promoted' => false,
            'national_master_verified' => false, 'production_admission' => 'DENIED',
        ];
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        if (! is_file($this->path) || ! is_readable($this->path)
            || filesize($this->path) > 512 * 1024) {
            throw new RuntimeException('AFFAIRS_SOURCE_UNAVAILABLE');
        }
        try {
            $data = json_decode((string) file_get_contents($this->path), true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('AFFAIRS_INVALID_JSON', 0, $e);
        }
        if (! is_array($data)
            || ($data['schema'] ?? null) !== 'PI_AFFAIR_FIRST_NOMENCLATURE_SEED_V1'
            || ! is_array($data['affairs'] ?? null) || count($data['affairs']) !== 32
            || ! is_array($data['nomenclature'] ?? null)
            || ! is_array($data['sectoral_triggers'] ?? null)
            || ! is_array($data['gates'] ?? null)) {
            throw new RuntimeException('AFFAIRS_SCHEMA_INVALID');
        }
        $groups = array_count_values(array_column($data['affairs'], 'group'));
        if (($groups['BASIC'] ?? 0) !== 6 || ($groups['NON_BASIC'] ?? 0) !== 18
            || ($groups['OPTIONAL'] ?? 0) !== 8) {
            throw new RuntimeException('AFFAIRS_CATEGORY_COUNTS_INVALID');
        }
        $ids = [];
        foreach ($data['affairs'] as $item) {
            if (! is_string($item['id'] ?? null) || isset($ids[$item['id']])
                || ! is_string($item['provision'] ?? null)
                || ! $this->safeSource($item['source_url'] ?? null)) {
                throw new RuntimeException('AFFAIRS_ENTRY_INVALID');
            }
            $ids[$item['id']] = true;
        }
        $seen = [];
        foreach ($data['nomenclature'] as $item) {
            $key = ($item['version'] ?? '').'|'.($item['year'] ?? '').'|'.($item['code'] ?? '');
            if (! isset($ids[$item['affair_id'] ?? '']) || isset($seen[$key])
                || ! is_int($item['year'] ?? null)
                || ! is_string($item['code'] ?? null)
                || ! $this->safeSource($item['source_url'] ?? null)) {
                throw new RuntimeException('NOMENCLATURE_ENTRY_INVALID');
            }
            $seen[$key] = true;
        }
        foreach ($data['sectoral_triggers'] as $trigger) {
            if (! isset($ids[$trigger['affair_id'] ?? ''])) {
                throw new RuntimeException('SECTORAL_TRIGGER_INVALID');
            }
            foreach ($trigger['source_candidates'] as $candidate) {
                if (! $this->safeSource($candidate['url'] ?? null)) {
                    throw new RuntimeException('SECTORAL_SOURCE_INVALID');
                }
            }
        }
        if (($data['gates']['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($data['gates']['production_admission'] ?? null) !== 'DENIED') {
            throw new RuntimeException('AFFAIRS_GATES_INVALID');
        }
        return $data;
    }

    private function safeSource(mixed $url): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user'], $parts['pass']) && ! isset($parts['port'])
            && in_array(strtolower((string) ($parts['host'] ?? '')), [
                'jdih.kemenkeu.go.id', 'jdih.jogjakota.go.id',
                'peraturan.bpk.go.id', 'peraturan.go.id', 'www.peraturan.go.id',
                'bkad.kepriprov.go.id',
            ], true);
    }
}
